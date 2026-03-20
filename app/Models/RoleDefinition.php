<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'permission_level',
        'applies_to_group_type_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'permission_level' => 'integer',
    ];

    public function groupType(): BelongsTo
    {
        return $this->belongsTo(GroupType::class, 'applies_to_group_type_id');
    }

    public function leaderRoles(): HasMany
    {
        return $this->hasMany(LeaderRole::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
