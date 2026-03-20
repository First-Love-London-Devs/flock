<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use Illuminate\Support\Collection;

class GroupHierarchyService
{
    public function getTree(?int $groupTypeId = null): Collection
    {
        $query = Group::with(['groupType', 'children.groupType', 'leader.member'])
            ->whereNull('parent_id')
            ->where('is_active', true);

        if ($groupTypeId) {
            $query->where('group_type_id', $groupTypeId);
        }

        return $query->get()->map(fn ($group) => $this->buildTreeNode($group));
    }

    protected function buildTreeNode(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'group_type' => $group->groupType?->name,
            'leader' => $group->leader?->member?->full_name,
            'member_count' => $group->members()->count(),
            'children' => $group->children
                ->where('is_active', true)
                ->map(fn ($child) => $this->buildTreeNode($child))
                ->values()
                ->toArray(),
        ];
    }

    public function getAncestors(int $groupId): Collection
    {
        $group = Group::find($groupId);
        if (!$group) {
            return collect();
        }

        return $group->ancestors();
    }

    public function getDescendants(int $groupId): Collection
    {
        $group = Group::find($groupId);
        if (!$group) {
            return collect();
        }

        return $group->descendants();
    }

    public function getGroupsAtLevel(int $level): Collection
    {
        $groupType = GroupType::where('level', $level)->first();
        if (!$groupType) {
            return collect();
        }

        return Group::where('group_type_id', $groupType->id)->where('is_active', true)->get();
    }

    public function moveGroup(int $groupId, ?int $newParentId): Group
    {
        $group = Group::findOrFail($groupId);

        if ($newParentId) {
            if ($newParentId === $groupId) {
                throw new \InvalidArgumentException('Cannot set group as its own parent.');
            }

            $parent = Group::findOrFail($newParentId);
            if ($parent->isDescendantOf($groupId)) {
                throw new \InvalidArgumentException('Cannot move group to its own descendant.');
            }
        }

        $group->update(['parent_id' => $newParentId]);

        return $group->fresh();
    }

    public function getMembersInSubtree(int $groupId): Collection
    {
        $group = Group::find($groupId);
        if (!$group) {
            return collect();
        }

        $groupIds = collect([$groupId])->merge($group->descendants()->pluck('id'));

        return Member::active()
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $groupIds))
            ->get();
    }
}
