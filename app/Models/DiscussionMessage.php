<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discussion_id',
        'user_id',
        'message_content',
        'message_type',
        'parent_message_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_type' => 'string',
        ];
    }

    /**
     * Get the discussion that owns the message.
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * Get the user who sent the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent message (for replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscussionMessage::class, 'parent_message_id');
    }

    /**
     * Get all replies to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class, 'parent_message_id');
    }

    /**
     * Scope to get only top-level messages (no parent).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_message_id');
    }

    /**
     * Scope to get messages of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('message_type', $type);
    }
}
