<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'leader_id', 'role_definition_id', 'group_id',
        'assigned_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Leader::class);
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(RoleDefinition::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
