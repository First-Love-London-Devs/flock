<?php

namespace Tests\Concerns;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use Carbon\Carbon;

trait BuildsGovernanceFixtures
{
    protected GroupType $constituencyType;
    protected GroupType $cellGroupType;
    protected RoleDefinition $bishopRole;
    protected RoleDefinition $governorRole;

    protected function seedGovernanceTypes(): void
    {
        $this->constituencyType = GroupType::factory()->constituency()->create();
        $this->cellGroupType = GroupType::factory()->cellGroup()->create();
        $this->bishopRole = RoleDefinition::factory()->bishop()->create();
        $this->governorRole = RoleDefinition::factory()->governor()
            ->state(['applies_to_group_type_id' => $this->constituencyType->id])
            ->create();
    }

    protected function makeConstituency(string $name = 'North Constituency'): Group
    {
        return Group::factory()->create([
            'name' => $name,
            'group_type_id' => $this->constituencyType->id,
            'parent_id' => null,
        ]);
    }

    protected function makeCellGroup(Group $constituency, ?Leader $leader = null, string $name = null): Group
    {
        return Group::factory()->create([
            'name' => $name ?? 'Group ' . fake()->word(),
            'group_type_id' => $this->cellGroupType->id,
            'parent_id' => $constituency->id,
            'leader_id' => $leader?->id,
        ]);
    }

    protected function makeMember(Group $cellGroup): Member
    {
        $member = Member::factory()->create();
        $member->groups()->attach($cellGroup->id, ['joined_at' => now(), 'is_primary' => true]);
        return $member;
    }

    protected function makeGovernor(Group $constituency): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->governorRole->id,
            'group_id' => $constituency->id,
            'is_active' => true,
        ]);
        return $leader;
    }

    protected function makeBishop(): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->bishopRole->id,
            'group_id' => null,
            'is_active' => true,
        ]);
        return $leader;
    }

    protected function submitAttendance(Group $cellGroup, Carbon $date, int $count = 30): AttendanceSummary
    {
        return AttendanceSummary::factory()->create([
            'group_id' => $cellGroup->id,
            'date' => $date->toDateString(),
            'total_attendance' => $count,
        ]);
    }
}
