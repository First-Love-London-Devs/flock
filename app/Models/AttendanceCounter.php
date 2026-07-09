<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCounter extends Model
{
    protected $fillable = [
        'group_id',
        'date',
        'first_time_count',
        'returning_count',
        'regular_count',
        'visitor_count',
        'reset_at',
    ];

    protected $casts = [
        'date' => 'date',
        'reset_at' => 'datetime',
        'first_time_count' => 'integer',
        'returning_count' => 'integer',
        'regular_count' => 'integer',
        'visitor_count' => 'integer',
    ];

    /**
     * The category keys the counter tracks, mapped to their summary columns.
     */
    public const CATEGORY_COLUMNS = [
        'first_time' => 'first_time_count',
        'returning' => 'returning_count',
        'regular' => 'regular_count',
        'visitor' => 'visitor_count',
    ];

    /**
     * The stream (or other group) this counter belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Grand total across every category.
     */
    public function getTotalCountAttribute(): int
    {
        return $this->first_time_count
            + $this->returning_count
            + $this->regular_count
            + $this->visitor_count;
    }
}
