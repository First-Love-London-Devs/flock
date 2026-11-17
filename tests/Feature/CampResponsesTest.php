<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\CampRegistrationController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CampRegistration;
use App\Models\Setting;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Link-only public view of the camp registrations (+ CSV) for organisers
 * without a Flock login. Gated by the same camp_enabled/camp_domain settings
 * as the form, plus an unguessable per-tenant share key in the URL.
 */
class CampResponsesTest extends TestCase
{
    private const KEY = 'k7Qm3vX9pLw2ZrT5bN8cH4dF6gJ1sA0eYuIoPaSd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        Setting::set('camp_enabled', true, 'boolean');
        Setting::set('camp_responses_key', self::KEY, 'string');
    }

    private function url(string $key = self::KEY, array $query = [], bool $csv = false): string
    {
        return '/camp/responses/'.$key.($csv ? '/csv' : '').($query ? '?'.http_build_query($query) : '');
    }

    private function copenhagen(string $name, array $extra = []): CampRegistration
    {
        return CampRegistration::create(array_merge([
            'full_name' => $name, 'phone_number' => '0700000000', 'camp' => 'copenhagen-2026',
            'church' => 'First Love Sweden', 'needs_accommodation' => false, 'bringing_children' => false,
        ], $extra));
    }

    public function test_page_is_404_unless_the_share_key_matches(): void
    {
        $this->get($this->url('wrong-key'))->assertNotFound();
        $this->get($this->url('wrong-key', csv: true))->assertNotFound();

        $this->get($this->url())->assertOk();

        // No key configured at all: nothing opens the page, not even a blank key.
        Setting::where('key', 'camp_responses_key')->delete();
        Setting::clearCache();
        $this->get($this->url())->assertNotFound();
        $this->get('/camp/responses/')->assertNotFound();
    }

    public function test_page_follows_the_camp_enabled_and_camp_domain_gates(): void
    {
        Setting::set('camp_domain', 'fl-eurozone.church-stack.com');
        $this->get('http://gochurch.church-stack.com'.$this->url())->assertNotFound();
        $this->get('http://fl-eurozone.church-stack.com'.$this->url())->assertOk();

        Setting::set('camp_enabled', false, 'boolean');
        $this->get('http://fl-eurozone.church-stack.com'.$this->url())->assertNotFound();
    }

    public function test_lists_current_camp_registrations_newest_first_and_hides_other_camps(): void
    {
        $older = $this->copenhagen('Astrid Older');
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $this->copenhagen('Bjorn Newer');
        CampRegistration::create(['full_name' => 'Heidi Basel', 'phone_number' => '1', 'camp' => 'basel-2026',
            'country' => 'Switzerland', 'denomination' => 'First Love']);

        $this->get($this->url())->assertOk()
            ->assertSee('2 registrations')
            ->assertSeeInOrder(['Bjorn Newer', 'Astrid Older'])
            ->assertDontSee('Heidi Basel')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_camp_filter_shows_basel_rows_with_a_church_label_derived_from_denomination_and_country(): void
    {
        $this->copenhagen('Astrid');
        CampRegistration::create(['full_name' => 'Heidi Basel', 'phone_number' => '1', 'camp' => 'basel-2026',
            'country' => 'Switzerland', 'denomination' => 'First Love']);

        $this->get($this->url(self::KEY, ['camp' => 'basel-2026']))->assertOk()
            ->assertSee('1 registration')
            ->assertSee('Heidi Basel')
            ->assertSee('First Love · Switzerland')
            ->assertDontSee('Astrid');
    }

    public function test_unknown_camp_filter_falls_back_to_the_current_camp(): void
    {
        $this->copenhagen('Astrid');

        $this->get($this->url(self::KEY, ['camp' => 'narnia-2030']))->assertOk()->assertSee('Astrid');
    }

    public function test_filters_by_church(): void
    {
        $this->copenhagen('Astrid Sweden', ['church' => 'First Love Sweden']);
        $this->copenhagen('Joao Portugal', ['church' => 'First Love Portugal']);

        $this->get($this->url(self::KEY, ['church' => 'First Love Portugal']))->assertOk()
            ->assertSee('Joao Portugal')->assertDontSee('Astrid Sweden');
    }

    public function test_filters_by_accommodation_and_children(): void
    {
        $this->copenhagen('Needs Bed', ['needs_accommodation' => true]);
        $this->copenhagen('Has Kids', ['bringing_children' => true, 'number_of_children' => 2]);
        $this->copenhagen('Neither');

        $this->get($this->url(self::KEY, ['accommodation' => '1']))->assertOk()
            ->assertSee('Needs Bed')->assertDontSee('Has Kids')->assertDontSee('Neither');
        $this->get($this->url(self::KEY, ['accommodation' => '0']))->assertOk()
            ->assertDontSee('Needs Bed')->assertSee('Has Kids')->assertSee('Neither');
        $this->get($this->url(self::KEY, ['children' => '1']))->assertOk()
            ->assertSee('Has Kids')->assertDontSee('Needs Bed')->assertDontSee('Neither');
    }

    public function test_searches_name_and_phone(): void
    {
        $this->copenhagen('Astrid Lindgren', ['phone_number' => '+46701234567']);
        $this->copenhagen('Joao Silva', ['phone_number' => '+351912345678']);

        $this->get($this->url(self::KEY, ['q' => 'lindgren']))->assertOk()
            ->assertSee('Astrid Lindgren')->assertDontSee('Joao Silva');
        $this->get($this->url(self::KEY, ['q' => '351912']))->assertOk()
            ->assertSee('Joao Silva')->assertDontSee('Astrid Lindgren');
        // LIKE wildcards in the query are literal, not magic.
        $this->get($this->url(self::KEY, ['q' => '%']))->assertOk()->assertSee('0 registrations');
    }

    public function test_csv_download_applies_the_same_filters(): void
    {
        $this->copenhagen('Astrid Lindgren', ['church' => 'First Love Sweden', 'email' => 'astrid@example.test',
            'travelling_from' => 'Malmö', 'needs_accommodation' => true, 'bringing_children' => true,
            'number_of_children' => 2, 'notes' => 'Vegetarian']);
        $this->copenhagen('Joao Silva', ['church' => 'First Love Portugal']);

        $response = $this->get($this->url(self::KEY, ['church' => 'First Love Sweden'], csv: true));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('copenhagen-camp-registrations.csv');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'UTF-8 BOM so Excel reads Malmö correctly');
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertSame(
            'Registered,"Full name",Phone,Email,Church,"Travelling from","Needs accommodation","Bringing children","Number of children",Notes',
            $lines[0]
        );
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('"Astrid Lindgren",0700000000,astrid@example.test,"First Love Sweden",Malmö,Yes,Yes,2,Vegetarian', $lines[1]);
        $this->assertStringNotContainsString('Joao Silva', $csv);
    }

    public function test_csv_neutralises_spreadsheet_formula_cells_but_leaves_phone_numbers_alone(): void
    {
        $this->copenhagen('=HYPERLINK("http://evil")', ['phone_number' => '+46701234567', 'notes' => '@cmd']);

        $csv = $this->get($this->url(csv: true))->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\"", $csv);
        $this->assertStringContainsString(',+46701234567,', $csv);
        $this->assertStringContainsString("'@cmd", $csv);
    }

    public function test_share_key_is_stable_until_rotated_and_revocation_closes_the_page(): void
    {
        Setting::where('key', 'camp_responses_key')->delete();
        Setting::clearCache();

        $first = CampRegistrationController::generateShareKey();
        $this->assertSame(40, strlen($first));
        $this->assertSame($first, CampRegistrationController::generateShareKey(), 'Re-running without rotate keeps the link working');
        $this->get($this->url($first))->assertOk();

        $second = CampRegistrationController::generateShareKey(rotate: true);
        $this->assertNotSame($first, $second);
        $this->get($this->url($first))->assertNotFound();
        $this->get($this->url($second))->assertOk();

        CampRegistrationController::revokeShareKey();
        $this->get($this->url($second))->assertNotFound();
        $this->assertNull(CampRegistrationController::shareKey());
    }

    public function test_an_organiser_can_delete_a_registration_from_the_page(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $keep = $this->copenhagen('Keep Me');
        $gone = $this->copenhagen('Remove Me');

        $this->get($this->url())->assertSee('Delete');

        $this->post('/camp/responses/'.self::KEY.'/delete/'.$gone->id, ['back' => '?church=First+Love+Sweden'])
            ->assertRedirect('/camp/responses/'.self::KEY.'?church=First+Love+Sweden');

        $this->assertDatabaseMissing('camp_registrations', ['id' => $gone->id]);
        $this->assertDatabaseHas('camp_registrations', ['id' => $keep->id]);
        $this->get($this->url())->assertSee('Removed Remove Me.')->assertDontSee('<strong>Remove Me</strong>', false);
    }

    public function test_delete_needs_the_share_key_and_the_camp_switched_on(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $row = $this->copenhagen('Stay Put');

        $this->post('/camp/responses/wrong-key/delete/'.$row->id)->assertNotFound();

        Setting::set('camp_enabled', false, 'boolean');
        $this->post('/camp/responses/'.self::KEY.'/delete/'.$row->id)->assertNotFound();

        $this->assertDatabaseHas('camp_registrations', ['id' => $row->id]);
    }

    public function test_delete_ignores_a_back_link_that_is_not_a_query_string(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $row = $this->copenhagen('Someone');

        $this->post('/camp/responses/'.self::KEY.'/delete/'.$row->id, ['back' => '//evil.example'])
            ->assertRedirect('/camp/responses/'.self::KEY);
    }
}
