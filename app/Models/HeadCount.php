<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadCount extends Model
{
    protected $fillable = [
        'group_id',
        'date',
        'total_attendance',
        'first_timer_count',
        'visitor_count',
        'submitter_name',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'total_attendance' => 'integer',
        'first_timer_count' => 'integer',
        'visitor_count' => 'integer',
    ];

    /**
     * The bacenta (attendance-tracking group) this count belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
