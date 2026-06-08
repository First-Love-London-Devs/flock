<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Leader extends Authenticatable
{
    use HasFactory, HasApiTokens, LogsActivity;

    protected $fillable = [
        'member_id', 'user_id', 'username', 'password', 'is_active', 'notification_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['username', 'is_active']);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function leaderRoles(): HasMany
    {
        return $this->hasMany(LeaderRole::class);
    }

    public function ledGroup(): HasOne
    {
        return $this->hasOne(Group::class, 'leader_id');
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->leaderRoles()
            ->where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', $roleSlug))
            ->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->leaderRoles()
            ->where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->exists();
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
