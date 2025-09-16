<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscussionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'environment_id' => $this->environment_id,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'course' => $this->whenLoaded('course', function () {
                return [
                    'id' => $this->course->id,
                    'title' => $this->course->title,
                    'slug' => $this->course->slug,
                ];
            }),
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'participant_count' => $this->when(
                $this->relationLoaded('participants'),
                fn() => $this->participants->count()
            ),
            'online_count' => $this->when(
                $this->relationLoaded('participants'),
                fn() => $this->participants->where('is_online', true)->count()
            ),
        ];
    }
}