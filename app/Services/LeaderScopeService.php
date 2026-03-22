<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Leader;
use Illuminate\Support\Collection;

class LeaderScopeService
{
    protected ?Collection $accessibleGroupIds = null;
    protected ?Leader $leader = null;

    public function setLeader(Leader $leader): static
    {
        $this->leader = $leader;
        $this->accessibleGroupIds = null;

        return $this;
    }

    public function getLeader(): ?Leader
    {
        return $this->leader;
    }

    public function isSuperAdmin(): bool
    {
        if (!$this->leader) {
            return false;
        }

        return $this->leader->leaderRoles()
            ->where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->where('permission_level', 100))
            ->exists();
    }

    public function getMaxPermissionLevel(): int
    {
        if (!$this->leader) {
            return 0;
        }

        return $this->leader->leaderRoles()
            ->where('is_active', true)
            ->join('role_definitions', 'leader_roles.role_definition_id', '=', 'role_definitions.id')
            ->max('role_definitions.permission_level') ?? 0;
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
            $this->accessibleGroupIds = Group::pluck('id');
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

        // Also include the group the leader directly leads
        if ($this->leader->ledGroup) {
            $allIds = $allIds->merge($this->leader->ledGroup->allGroupIds());
        }

        $this->accessibleGroupIds = $allIds->unique()->values();

        return $this->accessibleGroupIds;
    }

    public function canAccessGroup(int $groupId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->getAccessibleGroupIds()->contains($groupId);
    }

    public function scopeGroupsQuery($query)
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('id', $this->getAccessibleGroupIds());
    }

    public function scopeMembersQuery($query)
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $this->getAccessibleGroupIds()));
    }
}
