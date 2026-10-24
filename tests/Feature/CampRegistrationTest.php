<?php

namespace Tests\Feature;

use App\Filament\Resources\CampRegistrationResource;
use App\Models\CampRegistration;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Setting;
use App\Models\User;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Public, no-payment camp RSVP + its scoped admin list. Mirrors the Understanding
 * Campaign welcome form. Tenancy middleware is disabled so the tenant routes run
 * against the test connection, exactly as the API feature tests do.
 */
class CampRegistrationTest extends TestCase
{
    private Group $switzerland;

    private Group $basel;

    private Group $belgium;

    private Group $brussels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        $countryType = GroupType::create(['name' => 'Country', 'slug' => 'country', 'level' => 0, 'tracks_attendance' => false, 'is_active' => true]);
        $streamType = GroupType::create(['name' => 'Stream', 'slug' => 'stream', 'level' => 2, 'tracks_attendance' => false, 'is_active' => true]);

        $this->switzerland = Group::create(['name' => 'Switzerland', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->basel = Group::create(['name' => 'Basel', 'group_type_id' => $streamType->id, 'parent_id' => $this->switzerland->id, 'is_active' => true]);
        $this->belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->brussels = Group::create(['name' => 'Brussels', 'group_type_id' => $streamType->id, 'parent_id' => $this->belgium->id, 'is_active' => true]);

        // The feature is off by default on every tenant; turn it on for the
        // public-facing tests (the eurozone tenant will carry this setting).
        Setting::set('camp_enabled', true, 'boolean');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ada Lovelace',
            'phone_number' => '0791234567',
            'email' => 'ada@example.test',
            'country_group_id' => $this->switzerland->id,
            'church_group_id' => $this->basel->id,
            'attending_dates' => ['2026-11-16', '2026-11-17'],
            'needs_accommodation' => '1',
            'bringing_children' => '0',
            'notes' => 'Vegetarian',
        ], $overrides);
    }

    public function test_feature_is_404_on_a_tenant_that_has_not_enabled_it(): void
    {
        Setting::set('camp_enabled', false, 'boolean');

        $this->get('/camp')->assertNotFound();
        $this->get('/camp/register')->assertNotFound();
        $this->post('/camp/register', $this->validPayload())->assertNotFound();
        $this->assertDatabaseCount('camp_registrations', 0);

        // And the admin resource stays hidden/inaccessible on that tenant.
        $this->assertFalse(CampRegistrationResource::canAccess());
        $this->assertFalse(CampRegistrationResource::shouldRegisterNavigation());

        Setting::set('camp_enabled', true, 'boolean');
        $this->assertTrue(CampRegistrationResource::canAccess());
    }

    public function test_landing_and_form_render(): void
    {
        $this->get('/camp')->assertOk()->assertSee('Living Waters & First Love Camp');
        $this->get('/camp/register')->assertOk()->assertSee('Switzerland')->assertSee('Basel');
    }

    public function test_valid_registration_is_stored(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload())
            ->assertRedirect('/camp/register')
            ->assertSessionHas('success', true);

        $this->assertDatabaseHas('camp_registrations', [
            'full_name' => 'Ada Lovelace',
            'church_group_id' => $this->basel->id,
            'country_group_id' => $this->switzerland->id,
            'needs_accommodation' => 1,
            'bringing_children' => 0,
        ]);

        $row = CampRegistration::first();
        $this->assertSame(['2026-11-16', '2026-11-17'], $row->attending_dates);
    }

    public function test_church_must_belong_to_the_selected_country(): void
    {
        // Basel is in Switzerland, but the form claims Belgium.
        $this->post('/camp/register', $this->validPayload(['country_group_id' => $this->belgium->id]))
            ->assertStatus(422);

        $this->assertDatabaseCount('camp_registrations', 0);
    }

    public function test_attending_dates_are_required_and_validated(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['attending_dates' => []]))
            ->assertSessionHasErrors('attending_dates');

        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['attending_dates' => ['2026-12-25']]))
            ->assertSessionHasErrors('attending_dates.0');

        $this->assertDatabaseCount('camp_registrations', 0);
    }

    public function test_number_of_children_is_dropped_when_not_bringing_children(): void
    {
        $this->post('/camp/register', $this->validPayload(['bringing_children' => '0', 'number_of_children' => '3']));

        $this->assertNull(CampRegistration::first()->number_of_children);
    }

    public function test_closed_registration_blocks_submission(): void
    {
        Setting::set('camp_registrations_closed', true, 'boolean');

        $this->post('/camp/register', $this->validPayload())
            ->assertRedirect('/camp/register')
            ->assertSessionHas('closed', true);

        $this->assertDatabaseCount('camp_registrations', 0);
        $this->get('/camp/register')->assertOk()->assertSee('Registration closed');
    }

    public function test_admin_list_is_scoped_to_the_admins_churches(): void
    {
        $baselReg = CampRegistration::create(['full_name' => 'Swiss', 'phone_number' => '1', 'church_group_id' => $this->basel->id, 'country_group_id' => $this->switzerland->id]);
        $brusselsReg = CampRegistration::create(['full_name' => 'Belgian', 'phone_number' => '2', 'church_group_id' => $this->brussels->id, 'country_group_id' => $this->belgium->id]);

        // A Switzerland-scoped admin sees only the Basel registration.
        $this->actingAs(User::create(['name' => 'CH Admin', 'email' => 'ch@example.test', 'password' => 'password', 'scope_group_id' => $this->switzerland->id]));
        $visible = CampRegistrationResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($visible->contains($baselReg->id));
        $this->assertFalse($visible->contains($brusselsReg->id));

        // The group-wide admin (no scope) sees both.
        $this->actingAs(User::create(['name' => 'Wide', 'email' => 'wide@example.test', 'password' => 'password', 'scope_group_id' => null]));
        $this->assertSame(2, CampRegistrationResource::getEloquentQuery()->count());
    }
}
