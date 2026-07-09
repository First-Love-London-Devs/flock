<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSchedule extends Model
{
    protected $fillable = [
        'stream_group_id', 'role_definition_id', 'day_of_week',
        'start_time', 'end_time', 'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    public function streamGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'stream_group_id');
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(RoleDefinition::class, 'role_definition_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
