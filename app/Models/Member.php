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
    use HasFactory, LogsActivity, SoftDeletes;

    public const MEMBER_TYPES = [
        'member' => 'Member',
        'visitor' => 'Visitor',
        'first_timer' => 'First Timer',
        'new_convert' => 'New Convert',
    ];

    public const NBS_STATUSES = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
    ];

    /**
     * Status values shared by every spiritual growth-track course
     * (New Believers School, Strong Christian, and the Schools).
     */
    public const GROWTH_TRACK_STATUSES = self::NBS_STATUSES;

    /**
     * The growth-track course milestones that use a status column.
     */
    public const GROWTH_TRACK_COLUMNS = [
        'strong_christian_status',
        'school_of_the_word_status',
        'school_of_solid_foundation_status',
        'school_of_victorious_living_status',
        'school_of_apologetics_status',
        'school_of_evangelism_status',
    ];

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number', 'date_of_birth',
        'gender', 'address', 'street_name', 'postal_code', 'picture',
        'marital_status', 'occupation',
        'nbs_status', 'holy_ghost_baptism', 'water_baptism',
        'strong_christian_status', 'school_of_the_word_status',
        'school_of_solid_foundation_status', 'school_of_victorious_living_status',
        'school_of_apologetics_status', 'school_of_evangelism_status',
        'member_type', 'profile_completed',
        'member_since', 'is_active', 'notes', 'additional_info',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'member_since' => 'date',
        'is_active' => 'boolean',
        'holy_ghost_baptism' => 'boolean',
        'water_baptism' => 'boolean',
        'profile_completed' => 'boolean',
        'additional_info' => 'array',
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
        return $this->first_name.' '.$this->last_name;
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->picture) {
            return $this->picture;
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->full_name).'&background=random';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
