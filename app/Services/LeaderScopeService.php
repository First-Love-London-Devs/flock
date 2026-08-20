<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Leader;
use App\Services\DomainScope;
use Illuminate\Support\Collection;

class LeaderScopeService
{
    protected ?Collection $accessibleGroupIds = null;
    protected ?Leader $leader = null;
    protected ?bool $cachedIsSuperAdmin = null;

    public function setLeader(Leader $leader): static
    {
        $this->leader = $leader;
        $this->accessibleGroupIds = null;
        $this->cachedIsSuperAdmin = null;

        return $this;
    }

    public function getLeader(): ?Leader
    {
        return $this->leader;
    }

    public function isSuperAdmin(): bool
    {
        if ($this->cachedIsSuperAdmin !== null) {
            return $this->cachedIsSuperAdmin;
        }

        if (!$this->leader) {
            return $this->cachedIsSuperAdmin = false;
        }

        return $this->cachedIsSuperAdmin = $this->leader->leaderRoles()
            ->where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->where('permission_level', 100))
            ->exists();
    }

    public function getAccessibleGroupIds(): Collection
    {
        if ($this->accessibleGroupIds !== null) {
            return $this->accessibleGroupIds;
        }

        if (!$this->leader) {
            $this->accessibleGroupIds = collect();
            return $this->accessibleGroupIds;
        }

        if ($this->isSuperAdmin()) {
            $this->accessibleGroupIds = $this->confine(Group::pluck('id'));
            return $this->accessibleGroupIds;
        }

        $assignedGroupIds = $this->leader->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->pluck('group_id');

        $allIds = collect();
        foreach ($assignedGroupIds as $groupId) {
            $group = Group::find($groupId);
            if ($group) {
                $allIds = $allIds->merge($group->allGroupIds());
            }
        }

        if ($this->leader->ledGroup) {
            $allIds = $allIds->merge($this->leader->ledGroup->allGroupIds());
        }

        $this->accessibleGroupIds = $this->confine($allIds->unique()->values());

        return $this->accessibleGroupIds;
    }

    /** Narrow to the country the request arrived through. @see DomainScope::confine */
    protected function confine(Collection $ids): Collection
    {
        return DomainScope::confine($ids);
    }

    public function canAccessGroup(int $groupId): bool
    {
        // Super admins are checked against the same narrowed set as anyone
        // else. Returning true unconditionally here would have let a group
        // admin act on Sweden while ostensibly inside Belgium, which is the
        // opposite of what a lens is for.
        return $this->getAccessibleGroupIds()->contains($groupId);
    }

    /* Both scopes below used to return the query untouched for a super
       admin. That is correct while a leader's reach is the only thing
       narrowing it, and wrong once a domain can narrow too: a group admin
       inside belgium. would have been handed every group and every member
       in all eight countries, while every other screen showed them
       Belgium. They now go through getAccessibleGroupIds() like anyone
       else, which is a no-op when no domain scope applies. */
    public function scopeGroupsQuery($query)
    {
        if ($this->isSuperAdmin() && DomainScope::groupIds() === null) {
            return $query;
        }

        return $query->whereIn('id', $this->getAccessibleGroupIds());
    }

    public function scopeMembersQuery($query)
    {
        if ($this->isSuperAdmin() && DomainScope::groupIds() === null) {
            return $query;
        }

        return $query->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $this->getAccessibleGroupIds()));
    }
}
