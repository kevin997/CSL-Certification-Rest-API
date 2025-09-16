<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discussion_id' => $this->discussion_id,
            'content' => $this->message_content,
            'type' => $this->message_type,
            'parent_message_id' => $this->parent_message_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'avatar' => $this->user->avatar_url ?? null,
                ];
            }),
            'parent' => $this->whenLoaded('parent', function () {
                return new MessageResource($this->parent);
            }),
            'replies_count' => $this->when(
                $this->relationLoaded('replies'),
                fn() => $this->replies->count()
            ),
        ];
    }
}