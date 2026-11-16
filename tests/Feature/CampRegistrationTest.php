<?php

namespace Tests\Feature;

use App\Filament\Resources\CampRegistrationResource;
use App\Models\CampRegistration;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Public, no-payment camp RSVP + its admin list. The church is a fixed short
 * list (not tenant Groups). Tenancy middleware is disabled so the tenant
 * routes run against the test connection, as the API feature tests do.
 */
class CampRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        // The feature is off by default on every tenant; turn it on for the
        // public-facing tests (the go-church tenant will carry this setting).
        Setting::set('camp_enabled', true, 'boolean');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ada Lovelace',
            'phone_number' => '0791234567',
            'email' => 'ada@example.test',
            'church' => 'First Love Sweden',
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

    public function test_landing_shows_the_copenhagen_camp_and_its_schedule(): void
    {
        $this->get('/camp')->assertOk()
            ->assertSee('Copenhagen Camp')
            ->assertSee('Copenhagen, Denmark')
            ->assertSee('camp-badge">Copenhagen<', false)
            ->assertDontSee('Dijon')
            ->assertSee('Sunday 13 December')
            ->assertSee('Monday 14 December')
            ->assertSee('Tuesday 15 December')
            ->assertSee('14 December 2026')
            ->assertDontSee('14–14')
            ->assertDontSee('Thursday 3 December')
            ->assertDontSee('Basel')
            ->assertDontSee('Living Waters &amp; First Love Camp');
    }

    public function test_form_offers_the_five_churches_and_no_denomination_or_country_picker(): void
    {
        $this->get('/camp/register')->assertOk()
            ->assertSee('14 December · Copenhagen')
            ->assertDontSee('14–14')
            ->assertDontSee('Dijon')
            ->assertSee('Go Church')
            ->assertSee('Go Ye Church')
            ->assertSee('First Love Sweden')
            ->assertSee('First Love Portugal')
            ->assertSee('First Love Bulgaria')
            ->assertDontSee('Denomination')
            ->assertDontSee('Select your country')
            ->assertDontSee('Germany');
    }

    public function test_camp_domain_setting_restricts_the_public_pages_to_one_host(): void
    {
        // The go-church tenant answers on two domains; the camp is a Eurozone
        // event, so it must only appear on the Eurozone host.
        Setting::set('camp_domain', 'fl-eurozone.church-stack.com');

        // The tenant's other domain: the whole feature is hidden.
        $this->get('http://gochurch.church-stack.com/camp')->assertNotFound();
        $this->get('http://gochurch.church-stack.com/camp/register')->assertNotFound();
        $this->post('http://gochurch.church-stack.com/camp/register', $this->validPayload())->assertNotFound();
        $this->assertDatabaseCount('camp_registrations', 0);

        // The camp host: served as normal.
        $this->get('http://fl-eurozone.church-stack.com/camp')->assertOk()->assertSee('Copenhagen Camp');
        $this->get('http://fl-eurozone.church-stack.com/camp/register')->assertOk();
    }

    public function test_valid_registration_is_stored_with_church_and_tagged_to_the_copenhagen_camp(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload())
            ->assertRedirect('/camp/register')
            ->assertSessionHas('success', true);

        $this->assertDatabaseHas('camp_registrations', [
            'full_name' => 'Ada Lovelace',
            'church' => 'First Love Sweden',
            'camp' => 'copenhagen-2026',
            'needs_accommodation' => 1,
            'bringing_children' => 0,
        ]);
    }

    public function test_church_must_be_one_of_the_fixed_options(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['church' => 'Narnia Chapel']))
            ->assertSessionHasErrors('church');

        $this->assertDatabaseCount('camp_registrations', 0);
    }

    public function test_church_is_required(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['church' => '']))
            ->assertSessionHasErrors('church');

        $this->assertDatabaseCount('camp_registrations', 0);
    }

    public function test_legacy_country_and_denomination_fields_are_ignored(): void
    {
        // The Basel-era form posted these; a stale tab or a bot replaying the
        // old payload must not be able to write them onto a Copenhagen row.
        $this->post('/camp/register', $this->validPayload([
            'country' => 'Germany',
            'denomination' => 'Living Waters',
        ]));

        $row = CampRegistration::sole();
        $this->assertNull($row->country);
        $this->assertNull($row->denomination);
        $this->assertSame('First Love Sweden', $row->church);
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

    public function test_admin_resource_lists_registrations_from_both_camps(): void
    {
        CampRegistration::create(['full_name' => 'Swiss', 'phone_number' => '1', 'camp' => 'basel-2026', 'country' => 'Switzerland', 'denomination' => 'First Love']);
        CampRegistration::create(['full_name' => 'Swede', 'phone_number' => '2', 'camp' => 'copenhagen-2026', 'church' => 'First Love Sweden']);

        $this->assertSame(2, CampRegistrationResource::getEloquentQuery()->count());
    }

    public function test_migration_tags_pre_existing_registrations_as_the_basel_camp(): void
    {
        // Re-create the state before the Copenhagen migration ran: Basel rows
        // exist, the camp/church columns do not. Then run only that migration.
        Schema::table('camp_registrations', fn ($t) => $t->dropColumn(['camp', 'church']));
        DB::table('camp_registrations')->insert([
            'full_name' => 'Heidi', 'phone_number' => '3', 'country' => 'Switzerland',
            'denomination' => 'First Love', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/tenant/2026_11_04_120000_add_camp_and_church_to_camp_registrations_table.php');
        $migration->up();

        $this->assertSame('basel-2026', DB::table('camp_registrations')->where('full_name', 'Heidi')->value('camp'));
        $this->assertNull(DB::table('camp_registrations')->where('full_name', 'Heidi')->value('church'));
    }
}
