<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCounterNotification extends Model
{
    protected $fillable = [
        'attendance_schedule_id', 'stream_group_id',
        'notification_date', 'status', 'sent_at',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AttendanceSchedule::class, 'attendance_schedule_id');
    }

    public static function hasBeenSent(int $scheduleId, string $date): bool
    {
        return static::where('attendance_schedule_id', $scheduleId)
            ->where('notification_date', $date)
            ->exists();
    }
}
