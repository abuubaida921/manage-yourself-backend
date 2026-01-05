<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Http\Resources\TaskResource;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::where('user_id', auth()->id())->active();

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->orderBy('due_date', 'asc')->paginate(15);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request)
    {
        $this->authorize('create', Task::class);

        $task = Task::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'priority' => $request->priority,
        ]);

        Log::info('Task created', ['task_id' => $task->id, 'user_id' => auth()->id()]);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $task->update($request->only(['title', 'description', 'due_date', 'status', 'priority']));

        Log::info('Task updated', ['task_id' => $task->id, 'user_id' => auth()->id()]);

        return new TaskResource($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $taskId = $task->id;
        $task->delete();

        Log::info('Task deleted', ['task_id' => $taskId, 'user_id' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ], 200);
    }

    /**
     * Get task summary/statistics for the authenticated user.
     */
    public function summary()
    {
        $userId = auth()->id();

        $summary = [
            'total' => Task::where('user_id', $userId)->count(),
            'pending' => Task::where('user_id', $userId)->pending()->count(),
            'completed' => Task::where('user_id', $userId)->completed()->count(),
            'overdue' => Task::where('user_id', $userId)->overdue()->count(),
            'due_soon' => Task::where('user_id', $userId)->dueSoon()->count(),
            'by_priority' => [
                'high' => Task::where('user_id', $userId)->where('priority', 'high')->pending()->count(),
                'medium' => Task::where('user_id', $userId)->where('priority', 'medium')->pending()->count(),
                'low' => Task::where('user_id', $userId)->where('priority', 'low')->pending()->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
