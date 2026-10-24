<?php

namespace Tests\Feature;

use App\Filament\Resources\CampRegistrationResource;
use App\Models\CampRegistration;
use App\Models\Setting;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Public, no-payment camp RSVP + its admin list. Country and denomination are
 * fixed short lists (not tenant Groups). Tenancy middleware is disabled so the
 * tenant routes run against the test connection, as the API feature tests do.
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
            'country' => 'Switzerland',
            'denomination' => 'First Love',
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
        $this->get('/camp/register')->assertOk()
            ->assertSee('Germany')->assertSee('Switzerland')
            ->assertSee('Denomination')->assertSee('First Love')->assertSee('Living Waters');
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
        $this->get('http://fl-eurozone.church-stack.com/camp')->assertOk()->assertSee('Living Waters & First Love Camp');
        $this->get('http://fl-eurozone.church-stack.com/camp/register')->assertOk();
    }

    public function test_valid_registration_is_stored(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload())
            ->assertRedirect('/camp/register')
            ->assertSessionHas('success', true);

        $this->assertDatabaseHas('camp_registrations', [
            'full_name' => 'Ada Lovelace',
            'country' => 'Switzerland',
            'denomination' => 'First Love',
            'needs_accommodation' => 1,
            'bringing_children' => 0,
        ]);
    }

    public function test_country_must_be_one_of_the_fixed_options(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['country' => 'Narnia']))
            ->assertSessionHasErrors('country');

        $this->assertDatabaseCount('camp_registrations', 0);
    }

    public function test_denomination_must_be_one_of_the_fixed_options(): void
    {
        $this->from('/camp/register')
            ->post('/camp/register', $this->validPayload(['denomination' => 'Something Else']))
            ->assertSessionHasErrors('denomination');

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

    public function test_admin_resource_lists_all_registrations(): void
    {
        CampRegistration::create(['full_name' => 'Swiss', 'phone_number' => '1', 'country' => 'Switzerland', 'denomination' => 'First Love']);
        CampRegistration::create(['full_name' => 'German', 'phone_number' => '2', 'country' => 'Germany', 'denomination' => 'Living Waters']);

        $this->assertSame(2, CampRegistrationResource::getEloquentQuery()->count());
    }
}
