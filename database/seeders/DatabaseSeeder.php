<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create sample tasks for the test user
        Task::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        Task::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        Task::factory()->count(2)->create([
            'user_id' => $user->id,
            'priority' => 'high',
            'status' => 'pending',
        ]);
    }
}
