<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Filament\Resources\UnderstandingCampaignResource;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\UnderstandingCampaign;
use App\Models\User;
use Tests\TestCase;

/**
 * A country admin sees their own country's welcome-form submissions, and only
 * their own country's streams in the filter.
 *
 * Belgium reported seeing Basel, Bern and Biel in the Stream dropdown, which
 * are Swiss. Two separate faults sat behind that, and only one of them was the
 * one being reported.
 */
class ScopingTest extends TestCase
{
    private Group $belgium;

    private Group $switzerland;

    private Group $antwerp;

    private Group $basel;

    private Group $antwerpBacenta;

    protected function setUp(): void
    {
        parent::setUp();

        $country = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $stream = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $bacenta = GroupType::create([
            'name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 4,
            'tracks_attendance' => true, 'is_active' => true,
        ]);

        $this->belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $country->id, 'is_active' => true]);
        $this->switzerland = Group::create(['name' => 'Switzerland', 'group_type_id' => $country->id, 'is_active' => true]);

        $this->antwerp = Group::create(['name' => 'Antwerp', 'group_type_id' => $stream->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);
        $this->basel = Group::create(['name' => 'Basel', 'group_type_id' => $stream->id,
            'parent_id' => $this->switzerland->id, 'is_active' => true]);

        $this->antwerpBacenta = Group::create(['name' => 'Antwerp Centre', 'group_type_id' => $bacenta->id,
            'parent_id' => $this->antwerp->id, 'is_active' => true]);
    }

    private function submission(Group $stream, string $name, ?Group $allocated = null): UnderstandingCampaign
    {
        return UnderstandingCampaign::create([
            'stream_id' => $stream->id,
            'allocated_group_id' => $allocated?->id,
            'attended_on' => '2026-08-23',
            'first_name' => $name,
            'last_name' => 'Visitor',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '2000',
            'phone_number' => '+32470000000',
            're_dedicating' => true,
            'first_time' => false,
            'who_invited' => 'A friend',
        ]);
    }

    private function adminFor(?Group $scope): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => strtolower($scope?->name ?? 'wide').'@example.test',
            'password' => 'password',
            'scope_group_id' => $scope?->id,
        ]);
    }

    private function visibleNames(): array
    {
        return UnderstandingCampaignResource::getEloquentQuery()
            ->pluck('first_name')->sort()->values()->all();
    }

    public function test_a_country_admin_does_not_see_another_countrys_submissions(): void
    {
        $this->submission($this->antwerp, 'Amara');
        $this->submission($this->basel, 'Heidi');

        $this->actingAs($this->adminFor($this->belgium));

        $this->assertSame(['Amara'], $this->visibleNames());
    }

    /**
     * ⚠ The fault that mattered most, and nobody reported it.
     *
     * A submission has no allocated group until somebody places it. Confining
     * on that column alone meant a country admin could not see the entries
     * they were supposed to be allocating: the work was invisible to the only
     * person who could do it.
     */
    public function test_a_country_admin_sees_submissions_that_have_not_been_allocated_yet(): void
    {
        $this->submission($this->antwerp, 'Unallocated');

        $this->actingAs($this->adminFor($this->belgium));

        $this->assertSame(['Unallocated'], $this->visibleNames());
    }

    /** And once allocated into their tree, it stays visible. */
    public function test_an_allocated_submission_is_still_visible(): void
    {
        $this->submission($this->antwerp, 'Placed', $this->antwerpBacenta);

        $this->actingAs($this->adminFor($this->belgium));

        $this->assertSame(['Placed'], $this->visibleNames());
    }

    public function test_the_group_wide_admin_sees_every_country(): void
    {
        $this->submission($this->antwerp, 'Amara');
        $this->submission($this->basel, 'Heidi');

        $this->actingAs($this->adminFor(null));

        $this->assertSame(['Amara', 'Heidi'], $this->visibleNames());
    }

    /**
     * The reported symptom: Swiss church names listed in a Belgian admin's
     * Stream filter. The rows were already confined, so this leaked names
     * rather than data, but a filter offering options that can never match is
     * broken regardless.
     */
    public function test_the_stream_filter_only_offers_the_admins_own_streams(): void
    {
        $this->actingAs($this->adminFor($this->belgium));

        $this->assertSame(['Antwerp'], UnderstandingCampaignResource::streamOptions()->values()->all());
    }

    public function test_the_group_wide_admin_can_filter_by_every_stream(): void
    {
        $this->actingAs($this->adminFor(null));

        $this->assertSame(['Antwerp', 'Basel'], UnderstandingCampaignResource::streamOptions()->values()->all());
    }
}
