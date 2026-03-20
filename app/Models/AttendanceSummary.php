<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'date', 'total_attendance', 'visitor_count',
        'first_timer_count', 'submitted_by_leader_id', 'notes', 'image',
    ];

    protected $casts = [
        'date' => 'date',
        'total_attendance' => 'integer',
        'visitor_count' => 'integer',
        'first_timer_count' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Leader::class, 'submitted_by_leader_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
