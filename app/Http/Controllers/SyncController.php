<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncController extends Controller
{
    /**
     * Sync tasks between client and server.
     *
     * This endpoint handles bidirectional sync with conflict resolution:
     * - Client sends its local changes with timestamps
     * - Server compares timestamps and resolves conflicts
     * - Server returns all changes since client's last sync
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'last_sync_at' => 'nullable|date',
            'tasks' => 'nullable|array',
            'tasks.*.client_id' => 'required|string',
            'tasks.*.server_id' => 'nullable|integer',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string|max:1000',
            'tasks.*.due_date' => 'required|date',
            'tasks.*.status' => 'required|in:pending,completed',
            'tasks.*.priority' => 'required|in:low,medium,high',
            'tasks.*.is_deleted' => 'nullable|boolean',
            'tasks.*.local_updated_at' => 'required|date',
            'tasks.*.version' => 'nullable|integer',
        ]);

        $userId = auth()->id();
        $lastSyncAt = $request->last_sync_at ? Carbon::parse($request->last_sync_at) : null;
        $clientTasks = $request->tasks ?? [];
        $syncedTasks = [];
        $conflicts = [];

        // Process each task from client
        foreach ($clientTasks as $clientTask) {
            $result = $this->processClientTask($userId, $clientTask);

            if ($result['status'] === 'conflict') {
                $conflicts[] = $result;
            } else {
                $syncedTasks[] = $result['task'];
            }
        }

        // Get all server changes since last sync
        $serverChanges = $this->getServerChanges($userId, $lastSyncAt);

        // Current server time for client to use as next sync reference
        $serverTime = now()->toIso8601String();

        Log::info('Sync completed', [
            'user_id' => $userId,
            'tasks_received' => count($clientTasks),
            'tasks_synced' => count($syncedTasks),
            'conflicts' => count($conflicts),
            'server_changes' => count($serverChanges),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'synced_tasks' => $syncedTasks,
                'server_changes' => $serverChanges,
                'conflicts' => $conflicts,
                'server_time' => $serverTime,
            ],
        ]);
    }

    /**
     * Process a single task from the client.
     */
    private function processClientTask(int $userId, array $clientTask): array
    {
        $clientUpdatedAt = Carbon::parse($clientTask['local_updated_at']);
        $isDeleted = $clientTask['is_deleted'] ?? false;
        $serverId = $clientTask['server_id'] ?? null;
        $clientId = $clientTask['client_id'];
        $clientVersion = $clientTask['version'] ?? 1;

        // Find existing task by server_id or client_id
        $existingTask = null;
        if ($serverId) {
            $existingTask = Task::where('id', $serverId)
                ->where('user_id', $userId)
                ->first();
        }

        if (!$existingTask) {
            $existingTask = Task::where('client_id', $clientId)
                ->where('user_id', $userId)
                ->first();
        }

        // New task - create it
        if (!$existingTask) {
            if ($isDeleted) {
                // Don't create a deleted task
                return [
                    'status' => 'skipped',
                    'task' => null,
                ];
            }

            $task = Task::create([
                'user_id' => $userId,
                'client_id' => $clientId,
                'title' => $clientTask['title'],
                'description' => $clientTask['description'] ?? null,
                'due_date' => $clientTask['due_date'],
                'status' => $clientTask['status'],
                'priority' => $clientTask['priority'],
                'is_deleted' => false,
                'version' => 1,
                'server_updated_at' => now(),
            ]);

            return [
                'status' => 'created',
                'task' => $this->formatTaskForSync($task),
            ];
        }

        // Existing task - check for conflicts using version and timestamps
        $serverUpdatedAt = $existingTask->server_updated_at
            ? Carbon::parse($existingTask->server_updated_at)
            : Carbon::parse($existingTask->updated_at);

        // Conflict detection:
        // If server version is higher than client version, there's a potential conflict
        // We use "last write wins" with timestamp comparison
        if ($existingTask->version > $clientVersion) {
            // Server has newer version - check timestamps
            if ($serverUpdatedAt->gt($clientUpdatedAt)) {
                // Server wins - return server version as conflict
                return [
                    'status' => 'conflict',
                    'client_task' => $clientTask,
                    'server_task' => $this->formatTaskForSync($existingTask),
                    'resolution' => 'server_wins',
                    'reason' => 'Server has newer timestamp',
                ];
            }
        }

        // Client wins or no conflict - update server
        if ($isDeleted) {
            $existingTask->update([
                'is_deleted' => true,
                'version' => $existingTask->version + 1,
                'server_updated_at' => now(),
            ]);
        } else {
            $existingTask->update([
                'title' => $clientTask['title'],
                'description' => $clientTask['description'] ?? null,
                'due_date' => $clientTask['due_date'],
                'status' => $clientTask['status'],
                'priority' => $clientTask['priority'],
                'is_deleted' => false,
                'version' => $existingTask->version + 1,
                'server_updated_at' => now(),
            ]);
        }

        return [
            'status' => 'updated',
            'task' => $this->formatTaskForSync($existingTask->fresh()),
        ];
    }

    /**
     * Get all server changes since last sync.
     */
    private function getServerChanges(int $userId, ?Carbon $lastSyncAt): array
    {
        $query = Task::where('user_id', $userId);

        if ($lastSyncAt) {
            $query->where(function ($q) use ($lastSyncAt) {
                $q->where('server_updated_at', '>', $lastSyncAt)
                  ->orWhere('updated_at', '>', $lastSyncAt);
            });
        }

        return $query->get()->map(function ($task) {
            return $this->formatTaskForSync($task);
        })->toArray();
    }

    /**
     * Format a task for sync response.
     */
    private function formatTaskForSync(Task $task): array
    {
        return [
            'server_id' => $task->id,
            'client_id' => $task->client_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date->toIso8601String(),
            'status' => $task->status,
            'priority' => $task->priority,
            'is_deleted' => (bool) $task->is_deleted,
            'version' => $task->version,
            'server_updated_at' => $task->server_updated_at
                ? Carbon::parse($task->server_updated_at)->toIso8601String()
                : $task->updated_at->toIso8601String(),
            'created_at' => $task->created_at->toIso8601String(),
        ];
    }

    /**
     * Get all tasks for initial sync (first time sync or full refresh).
     */
    public function fullSync(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $tasks = Task::where('user_id', $userId)
            ->where('is_deleted', false)
            ->get()
            ->map(function ($task) {
                return $this->formatTaskForSync($task);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get changes since a specific timestamp (pull-only sync).
     */
    public function pull(Request $request): JsonResponse
    {
        $request->validate([
            'since' => 'required|date',
        ]);

        $userId = auth()->id();
        $since = Carbon::parse($request->since);

        $changes = $this->getServerChanges($userId, $since);

        return response()->json([
            'success' => true,
            'data' => [
                'changes' => $changes,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}

