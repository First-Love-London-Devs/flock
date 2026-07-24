<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use App\Models\UnderstandingCampaign;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class UnderstandingCampaignAssignmentTest extends TestCase
{
    private RoleDefinition $ucRole;
    private GroupType $gsType;
    private GroupType $bacentaType;

    protected function setUp(): void
    {
        parent::setUp();
        // Matches every other Feature/Api test hitting routes/tenant.php: the
        // domain-tenancy middleware needs a real tenant domain to resolve,
        // which these tests do not set up, so it is disabled here.
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
        // The tenant seed migration already inserts this role under
        // RefreshDatabase, so firstOrCreate (not create) avoids a unique-slug
        // collision while still giving the test the row it needs.
        $this->ucRole = RoleDefinition::firstOrCreate(
            ['slug' => 'understanding-campaign'],
            ['name' => 'Understanding Campaign', 'permission_level' => 40, 'applies_to_group_type_id' => null, 'is_active' => true],
        );
        $this->gsType = GroupType::create(['name' => 'Gathering Service', 'slug' => 'gs', 'level' => 1, 'tracks_attendance' => false, 'is_active' => true]);
        $this->bacentaType = GroupType::create(['name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 2, 'tracks_attendance' => true, 'is_active' => true]);
    }

    private function gatheringService(string $name = 'GS A'): Group
    {
        return Group::create(['name' => $name, 'group_type_id' => $this->gsType->id, 'parent_id' => null]);
    }

    private function bacenta(Group $parent, string $name): Group
    {
        return Group::create(['name' => $name, 'group_type_id' => $this->bacentaType->id, 'parent_id' => $parent->id]);
    }

    private function repFor(Group $gs): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::create(['leader_id' => $leader->id, 'role_definition_id' => $this->ucRole->id, 'group_id' => $gs->id, 'is_active' => true]);
        return $leader;
    }

    private function record(Group $stream, array $overrides = []): UnderstandingCampaign
    {
        return UnderstandingCampaign::create(array_merge([
            'stream_id' => $stream->id, 'attended_on' => now()->toDateString(),
            'first_name' => 'Ama', 'last_name' => 'Owusu', 'street_name' => '1 High St',
            'postal_code' => 'AB1 2CD', 'phone_number' => '07000000000',
            'first_time' => true, 're_dedicating' => false, 'who_invited' => 'A Friend',
        ], $overrides));
    }

    public function test_rep_sees_only_records_in_their_gathering_service_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $bacenta = $this->bacenta($gs, 'Bacenta 1');
        $mine = $this->record($bacenta);

        $otherGs = $this->gatheringService('GS B');
        $otherBacenta = $this->bacenta($otherGs, 'Bacenta 2');
        $this->record($otherBacenta, ['first_name' => 'NotMine']);

        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_unassigned_status_filter_excludes_already_assigned(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $b2 = $this->bacenta($gs, 'B2');
        $unassigned = $this->record($b1);
        $assigned = $this->record($b1, ['allocated_group_id' => $b2->id, 'first_name' => 'Done']);
        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns?status=unassigned');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($unassigned->id, $ids);
        $this->assertNotContains($assigned->id, $ids);
    }

    public function test_assignable_groups_returns_only_tracks_attendance_groups_in_subtree(): void
    {
        $gs = $this->gatheringService();
        $bacenta = $this->bacenta($gs, 'Real Bacenta');
        // a non-tracks_attendance child should be excluded
        $subService = Group::create(['name' => 'Sub', 'group_type_id' => $this->gsType->id, 'parent_id' => $gs->id]);

        // a tracks_attendance bacenta in a DIFFERENT gathering service should
        // also be excluded, proving the endpoint scopes to the caller's
        // subtree and not just to tracks_attendance groups anywhere.
        $otherGs = $this->gatheringService('GS Other');
        $otherBacenta = $this->bacenta($otherGs, 'Other Bacenta');

        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns/assignable-groups');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertSame([$bacenta->id], $ids);
    }

    public function test_leader_without_the_role_is_forbidden(): void
    {
        $leader = Leader::factory()->create();
        $this->actingAs($leader, 'sanctum')
            ->getJson('/api/v1/understanding-campaigns')
            ->assertForbidden();
    }

    public function test_rep_can_assign_an_in_scope_record_to_an_in_scope_bacenta(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $target = $this->bacenta($gs, 'Target');
        $record = $this->record($b1);
        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $target->id]);

        $res->assertOk();
        $this->assertSame($target->id, $res->json('data.allocated_group.id'));
        $this->assertDatabaseHas('understanding_campaigns', ['id' => $record->id, 'allocated_group_id' => $target->id]);
    }

    public function test_null_clears_the_assignment(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1, ['allocated_group_id' => $b1->id]);
        $rep = $this->repFor($gs);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => null])
            ->assertOk();
        $this->assertDatabaseHas('understanding_campaigns', ['id' => $record->id, 'allocated_group_id' => null]);
    }

    public function test_cannot_assign_a_record_outside_the_reps_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $target = $this->bacenta($gs, 'Target');
        $rep = $this->repFor($gs);

        $otherGs = $this->gatheringService('GS B');
        $otherBacenta = $this->bacenta($otherGs, 'Other');
        $foreign = $this->record($otherBacenta);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$foreign->id}/assign", ['allocated_group_id' => $target->id])
            ->assertForbidden();
    }

    public function test_cannot_assign_into_a_group_outside_the_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1);
        $rep = $this->repFor($gs);

        $otherGs = $this->gatheringService('GS B');
        $foreignBacenta = $this->bacenta($otherGs, 'Foreign');

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $foreignBacenta->id])
            ->assertStatus(422);
    }

    public function test_cannot_assign_into_a_non_tracks_attendance_group(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1);
        $nonBacenta = Group::create(['name' => 'Sub GS', 'group_type_id' => $this->gsType->id, 'parent_id' => $gs->id]);
        $rep = $this->repFor($gs);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $nonBacenta->id])
            ->assertStatus(422);
    }

    public function test_missing_allocated_group_id_key_is_rejected_and_does_not_change_state(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1, ['allocated_group_id' => $b1->id]);
        $rep = $this->repFor($gs);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", [])
            ->assertStatus(422);

        // The prior assignment must be untouched.
        $this->assertDatabaseHas('understanding_campaigns', ['id' => $record->id, 'allocated_group_id' => $b1->id]);
    }
}
