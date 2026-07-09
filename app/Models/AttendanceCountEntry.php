<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCountEntry extends Model
{
    protected $fillable = [
        'group_id',
        'date',
        'device_id',
        'category',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * The stream (or other group) this tap was recorded against.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
