<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Models\Group;
use App\Models\GroupType;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class WelcomeFormTest extends TestCase
{
    private Group $stream;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        $streamType = GroupType::factory()->create(['name' => 'Stream', 'slug' => 'stream']);
        $this->stream = Group::factory()->create([
            'name' => 'Gospel Experience Service',
            'group_type_id' => $streamType->id,
            'parent_id' => null,
        ]);
    }

    private function slug(): string
    {
        return 'gospel-experience-service';
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

    public function test_landing_lists_the_streams(): void
    {
        $this->get('/welcome')
            ->assertOk()
            ->assertSee('Gospel Experience Service')
            ->assertSee('/welcome/'.$this->slug(), false);
    }

    public function test_per_stream_form_renders(): void
    {
        $this->get('/welcome/'.$this->slug())
            ->assertOk()
            ->assertSee('Gospel Experience Service')
            ->assertSee('Voornaam');
    }

    public function test_unknown_stream_is_404(): void
    {
        $this->get('/welcome/not-a-real-stream')->assertNotFound();
    }

    public function test_valid_submission_is_stored_with_stream(): void
    {
        $this->post('/welcome/'.$this->slug(), $this->payload())
            ->assertRedirect('/welcome/'.$this->slug())
            ->assertSessionHas('success');

        $this->assertDatabaseHas('understanding_campaigns', [
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'stream_id' => $this->stream->id,
            're_dedicating' => true,
            'first_time' => true,
        ]);
    }

    public function test_missing_required_field_is_rejected(): void
    {
        $this->post('/welcome/'.$this->slug(), $this->payload(['first_name' => '']))
            ->assertSessionHasErrors('first_name');

        $this->assertDatabaseCount('understanding_campaigns', 0);
    }
}
