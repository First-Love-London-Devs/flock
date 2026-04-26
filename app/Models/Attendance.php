<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_summary_id', 'member_id', 'attended', 'is_first_timer', 'is_visitor', 'is_new_convert',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'is_first_timer' => 'boolean',
        'is_visitor' => 'boolean',
        'is_new_convert' => 'boolean',
    ];

    public function attendanceSummary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
