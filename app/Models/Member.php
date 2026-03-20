<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number', 'date_of_birth',
        'gender', 'address', 'picture', 'marital_status', 'occupation',
        'member_since', 'is_active', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'member_since' => 'date',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['first_name', 'last_name', 'is_active']);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_member')
            ->withPivot('joined_at', 'left_at', 'is_primary')
            ->withTimestamps();
    }

    public function leader(): HasOne
    {
        return $this->hasOne(Leader::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
