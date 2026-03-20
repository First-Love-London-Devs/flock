<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayNotification extends Model
{
    protected $fillable = [
        'member_id', 'leader_id', 'notification_date',
        'notification_type', 'status', 'sent_at',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Leader::class);
    }

    public static function hasBeenSent(int $memberId, int $leaderId, string $date, string $type): bool
    {
        return static::where('member_id', $memberId)
            ->where('leader_id', $leaderId)
            ->where('notification_date', $date)
            ->where('notification_type', $type)
            ->exists();
    }

    public function markAsSent(): void
    {
        $this->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('notification_date', $date);
    }

    public function scopeUnsent($query)
    {
        return $query->where('status', 'pending');
    }
}
