<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'data' => $this->data,
            'read' => $this->read_at != null,
            'created_at' => $this->created_at->diffForHumans(), // Cast to formatted string
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }
}
