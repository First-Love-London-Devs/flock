<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonMemberAttendance extends Model
{
    protected $fillable = [
        'attendance_summary_id',
        'non_member_id',
        'attended',
        'is_first_timer',
        'is_new_convert',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'is_first_timer' => 'boolean',
        'is_new_convert' => 'boolean',
    ];

    public function summary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class, 'attendance_summary_id');
    }

    public function nonMember(): BelongsTo
    {
        return $this->belongsTo(NonMember::class);
    }
}
