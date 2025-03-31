<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventRegistration extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_content_id',
        'user_id',
        'status', // registered, attended, cancelled, no-show
        'registration_date',
        'cancellation_date',
        'cancellation_reason',
        'attendance_confirmed_at',
        'attendance_confirmed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'datetime',
            'cancellation_date' => 'datetime',
            'attendance_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the event content that this registration belongs to.
     */
    public function eventContent(): BelongsTo
    {
        return $this->belongsTo(EventContent::class);
    }

    /**
     * Get the user who registered for this event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who confirmed the attendance.
     */
    public function attendanceConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_confirmed_by');
    }
}
