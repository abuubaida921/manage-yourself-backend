<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Passport;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_task(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Test Task',
            'description' => 'Test description',
            'due_date' => now()->addDay()->toIso8601String(),
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'due_date',
                    'status',
                    'priority',
                    'is_overdue',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_list_tasks(): void
    {
        Passport::actingAs($this->user);

        Task::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_user_can_view_own_task(): void
    {
        Passport::actingAs($this->user);

        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_user_cannot_view_others_task(): void
    {
        Passport::actingAs($this->user);

        $otherUser = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_own_task(): void
    {
        Passport::actingAs($this->user);

        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_user_can_delete_own_task(): void
    {
        Passport::actingAs($this->user);

        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_user_can_filter_tasks_by_priority(): void
    {
        Passport::actingAs($this->user);

        Task::factory()->create(['user_id' => $this->user->id, 'priority' => 'high']);
        Task::factory()->create(['user_id' => $this->user->id, 'priority' => 'low']);

        $response = $this->getJson('/api/v1/tasks?priority=high');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_user_can_filter_tasks_by_status(): void
    {
        Passport::actingAs($this->user);

        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);
        Task::factory()->create(['user_id' => $this->user->id, 'status' => 'completed']);

        $response = $this->getJson('/api/v1/tasks?status=pending');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_validation_fails_for_invalid_task_data(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => '',
            'due_date' => 'invalid-date',
            'priority' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(401);
    }
}

