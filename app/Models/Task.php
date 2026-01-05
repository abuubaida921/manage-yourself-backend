<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'due_date',
        'status',
        'priority',
        'is_deleted',
        'version',
        'server_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'server_updated_at' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending tasks.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('is_deleted', false);
    }

    /**
     * Scope for completed tasks.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed')->where('is_deleted', false);
    }

    /**
     * Scope for overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->where('status', 'pending')
                     ->where('is_deleted', false);
    }

    /**
     * Scope for tasks due soon (within 24 hours).
     */
    public function scopeDueSoon($query)
    {
        return $query->where('due_date', '>', now())
                     ->where('due_date', '<=', now()->addDay())
                     ->where('status', 'pending')
                     ->where('is_deleted', false);
    }

    /**
     * Scope for active (non-deleted) tasks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }
}
