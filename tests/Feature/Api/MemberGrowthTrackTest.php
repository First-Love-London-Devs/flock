<?php

namespace Tests\Feature\Api;

use App\Models\Member;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class MemberGrowthTrackTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
        $this->seedGovernanceTypes();
    }

    private function actorWithMember(): array
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        $member = $this->makeMember($cell);

        return [$governor, $member];
    }

    public function test_growth_track_columns_exist_and_default_to_null(): void
    {
        [, $member] = $this->actorWithMember();
        $member->refresh();

        foreach (Member::GROWTH_TRACK_COLUMNS as $column) {
            $this->assertNull($member->{$column});
        }
    }

    public function test_leader_can_update_growth_track_statuses(): void
    {
        [$governor, $member] = $this->actorWithMember();

        $this->actingAs($governor, 'sanctum')
            ->putJson("/api/v1/members/{$member->id}", [
                'strong_christian_status' => 'in_progress',
                'school_of_the_word_status' => 'completed',
                'school_of_evangelism_status' => 'not_started',
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.strong_christian_status', 'in_progress')
            ->assertJsonPath('data.school_of_the_word_status', 'completed');

        $member->refresh();
        $this->assertSame('in_progress', $member->strong_christian_status);
        $this->assertSame('completed', $member->school_of_the_word_status);
        $this->assertSame('not_started', $member->school_of_evangelism_status);
    }

    public function test_invalid_growth_track_status_is_rejected(): void
    {
        [$governor, $member] = $this->actorWithMember();

        $this->actingAs($governor, 'sanctum')
            ->putJson("/api/v1/members/{$member->id}", [
                'school_of_apologetics_status' => 'graduated',
            ])
            ->assertStatus(422);
    }

    public function test_show_returns_growth_track_fields(): void
    {
        [$governor, $member] = $this->actorWithMember();
        $member->update(['school_of_solid_foundation_status' => 'completed']);

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.school_of_solid_foundation_status', 'completed');
    }
}
