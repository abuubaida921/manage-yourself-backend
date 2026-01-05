<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date->toIso8601String(),
            'status' => $this->status,
            'priority' => $this->priority,
            'is_overdue' => $this->due_date->isPast() && $this->status === 'pending',
            'is_deleted' => (bool) $this->is_deleted,
            'version' => $this->version,
            'server_updated_at' => $this->server_updated_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Customize the response for a resource.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
