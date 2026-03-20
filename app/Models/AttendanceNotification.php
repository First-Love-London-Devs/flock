<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceNotification extends Model
{
    protected $fillable = [
        'parent_group_id', 'date', 'total_attendance',
        'notification_type', 'leader_id', 'status', 'sent_at',
    ];

    protected $casts = [
        'date' => 'date',
        'sent_at' => 'datetime',
        'total_attendance' => 'integer',
    ];

    public function parentGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_group_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Leader::class);
    }

    public static function hasBeenSent(int $parentGroupId, string $date, string $type = 'completion'): bool
    {
        return static::where('parent_group_id', $parentGroupId)
            ->where('date', $date)
            ->where('notification_type', $type)
            ->exists();
    }

    public static function markAsSent(int $parentGroupId, string $date, int $totalAttendance, ?int $leaderId, string $type = 'completion'): static
    {
        return static::create([
            'parent_group_id' => $parentGroupId,
            'date' => $date,
            'total_attendance' => $totalAttendance,
            'notification_type' => $type,
            'leader_id' => $leaderId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
