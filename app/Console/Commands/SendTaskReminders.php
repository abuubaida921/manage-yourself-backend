<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for tasks due within the next hour';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tasks = \App\Models\Task::where('due_date', '>', now())
            ->where('due_date', '<=', now()->addHour())
            ->where('status', 'pending')
            ->get();

        foreach ($tasks as $task) {
            $task->user->notify(new \App\Notifications\TaskReminder($task));
        }

        $this->info('Reminders sent for ' . $tasks->count() . ' tasks.');
    }
}
