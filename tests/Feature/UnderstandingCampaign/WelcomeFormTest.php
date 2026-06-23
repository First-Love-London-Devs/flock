<?php

namespace Tests\Feature\UnderstandingCampaign;

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class WelcomeFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => '1',
            'first_time' => '1',
            'who_invited' => 'Piet',
        ], $overrides);
    }

    public function test_form_renders_with_dutch_labels(): void
    {
        $this->get('/welcome')
            ->assertOk()
            ->assertSee('Voornaam')
            ->assertSee('Wie heeft jou uitgenodigd?');
    }

    public function test_valid_submission_is_stored(): void
    {
        $this->post('/welcome', $this->payload())
            ->assertRedirect('/welcome')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('understanding_campaigns', [
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            're_dedicating' => true,
            'first_time' => true,
            'who_invited' => 'Piet',
        ]);
    }

    public function test_missing_required_field_is_rejected(): void
    {
        $this->post('/welcome', $this->payload(['first_name' => '']))
            ->assertSessionHasErrors('first_name');

        $this->assertDatabaseCount('understanding_campaigns', 0);
    }
}
