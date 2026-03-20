<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'level', 'color', 'icon',
        'tracks_attendance', 'is_active',
    ];

    protected $casts = [
        'tracks_attendance' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function roleDefinitions(): HasMany
    {
        return $this->hasMany(RoleDefinition::class, 'applies_to_group_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
