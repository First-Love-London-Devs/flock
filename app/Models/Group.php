<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'group_type_id', 'parent_id', 'leader_id', 'description',
        'meeting_day', 'meeting_time', 'address', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meeting_day' => 'integer',
    ];

    private ?Collection $cachedDescendantIds = null;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'leader_id', 'parent_id', 'is_active']);
    }

    public function groupType(): BelongsTo
    {
        return $this->belongsTo(GroupType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Leader::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'group_member')
            ->withPivot('joined_at', 'left_at', 'is_primary')
            ->withTimestamps();
    }

    public function leaderRoles(): HasMany
    {
        return $this->hasMany(LeaderRole::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByType($query, int $groupTypeId)
    {
        return $query->where('group_type_id', $groupTypeId);
    }

    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;
        $maxDepth = 20;

        while ($current && $maxDepth-- > 0) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    public function descendantIds(): Collection
    {
        if ($this->cachedDescendantIds !== null) {
            return $this->cachedDescendantIds;
        }

        $ids = collect();
        $parentIds = collect([$this->id]);

        $maxDepth = 20;
        while ($parentIds->isNotEmpty() && $maxDepth-- > 0) {
            $childIds = DB::table('groups')
                ->whereIn('parent_id', $parentIds)
                ->whereNull('deleted_at')
                ->pluck('id');

            $ids = $ids->merge($childIds);
            $parentIds = $childIds;
        }

        $this->cachedDescendantIds = $ids;

        return $this->cachedDescendantIds;
    }

    public function descendants(): Collection
    {
        return static::whereIn('id', $this->descendantIds())->get();
    }

    public function allGroupIds(): Collection
    {
        return $this->descendantIds()->push($this->id);
    }

    public function isDescendantOf(int $groupId): bool
    {
        return $this->ancestors()->pluck('id')->contains($groupId);
    }

    public function getTotalMembersCountAttribute(): int
    {
        return Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $this->allGroupIds()))->count();
    }
}
