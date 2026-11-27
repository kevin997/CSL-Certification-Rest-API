<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademyWorkflow extends Model
{
    protected $fillable = ['environment_id', 'draft', 'published', 'published_trigger', 'revision', 'draft_version', 'active', 'published_at', 'activated_at_us'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'active' => 'boolean', 'published_at' => 'datetime'];
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->active ? 'active' : ($this->published ? 'paused' : 'draft'),
            'draft' => $this->draft,
            'draft_version' => $this->draft_version,
            'published' => $this->published,
            'revision' => $this->revision,
            'published_at' => $this->published_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
