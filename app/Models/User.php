<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'central') {
            return $this->is_super_admin ?? false;
        }

        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'scope_group_id',
    ];

    /** The country (or any group) this admin is confined to, if any. */
    public function scopeGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'scope_group_id');
    }

    /**
     * Group ids this admin may see, or null for the whole tenant.
     *
     * Null rather than "all ids" on purpose, so callers can tell the
     * difference between "unrestricted" and "restricted to nothing" and
     * skip the filter entirely in the first case.
     */
    public function scopedGroupIds(): ?Collection
    {
        if (! $this->scope_group_id) {
            return null;
        }

        $group = $this->scopeGroup;

        return $group ? $group->allGroupIds() : collect();
    }

    /**
     * The panel user for the current request, if one is signed in.
     *
     * Filament resources are static, so this is where they reach for the
     * scope rather than each one repeating the auth lookup.
     */
    public static function currentScopeIds(): ?Collection
    {
        $user = auth()->user();

        return $user instanceof self ? $user->scopedGroupIds() : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
        'scope_group_id' => 'integer',
    ];
}
