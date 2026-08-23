<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceCounterResource;
use App\Models\AttendanceCountEntry;
use App\Models\AttendanceCounter;
use App\Models\Group;
use App\Models\GroupType;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Correcting a miscount.
 *
 * Two different mistakes, two different remedies. A stray tap during the count
 * is undone at the kiosk, and the tap log has to move with the total or the
 * count and its own history start disagreeing. A wrong figure discovered
 * afterwards is corrected by an admin, and there the tap log is deliberately
 * left alone: it is what actually happened.
 */
class AttendanceCorrectionTest extends TestCase
{
    private Group $stream;

    protected function setUp(): void
    {
        parent::setUp();

        /* These routes live on the tenant domain. The suite runs against the
           tenant schema directly, so the domain-resolution middleware has
           nothing to resolve and must stand aside, as in WelcomeFormTest. */
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        $type = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $this->stream = Group::create([
            'name' => 'Jesus Encounter Service', 'group_type_id' => $type->id, 'is_active' => true,
        ]);

        // Cached per request in production; per process in tests.
        \App\Services\DomainScope::forget();
    }

    protected function tearDown(): void
    {
        \App\Services\DomainScope::forget();
        parent::tearDown();
    }

    /* Paths, not route(): tenant route names do not resolve through the
       helper in this app, and the controller matches the stream on a slug of
       its name rather than an id. */
    private function path(string $action): string
    {
        return '/attendance-counter/'.\Illuminate\Support\Str::slug($this->stream->name).'/'.$action;
    }

    private function tap(string $category = 'first_time'): void
    {
        $this->postJson($this->path('increment'), [
            'category' => $category, 'device_id' => 'usher-1',
        ])->assertOk();
    }

    private function undo(string $category = 'first_time')
    {
        return $this->postJson($this->path('undo'), ['category' => $category]);
    }

    /**
     * The counter is public, so the address it was opened at is the only thing
     * available to scope it. It listed every stream in the tenant, which went
     * unnoticed while Belgium was the only country that had any: the morning
     * Switzerland got eight, Belgium's ushers were offered Swiss churches.
     */
    public function test_the_landing_page_only_offers_streams_from_this_addresss_country(): void
    {
        $country = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $type = GroupType::where('slug', 'stream')->firstOrFail();

        $belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $country->id, 'is_active' => true]);
        Group::create(['name' => 'Gospel Experience Service', 'group_type_id' => $type->id,
            'parent_id' => $belgium->id, 'is_active' => true]);

        $swiss = Group::create(['name' => 'Switzerland', 'group_type_id' => $country->id, 'is_active' => true]);
        Group::create(['name' => 'Basel', 'group_type_id' => $type->id,
            'parent_id' => $swiss->id, 'is_active' => true]);

        \App\Services\DomainScope::fake('Belgium');

        $this->get('/attendance-counter')
            ->assertOk()
            ->assertSee('Gospel Experience Service')
            ->assertDontSee('Basel');
    }

    public function test_a_counter_from_another_country_cannot_be_opened_by_url(): void
    {
        $country = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $type = GroupType::where('slug', 'stream')->firstOrFail();
        $belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $country->id, 'is_active' => true]);
        Group::create(['name' => 'Gospel Experience Service', 'group_type_id' => $type->id,
            'parent_id' => $belgium->id, 'is_active' => true]);
        $swiss = Group::create(['name' => 'Switzerland', 'group_type_id' => $country->id, 'is_active' => true]);
        Group::create(['name' => 'Basel', 'group_type_id' => $type->id,
            'parent_id' => $swiss->id, 'is_active' => true]);

        \App\Services\DomainScope::fake('Belgium');

        // Guessing the URL must not work either, or the list is only a curtain.
        $this->get('/attendance-counter/basel')->assertNotFound();
        $this->post('/attendance-counter/basel/increment', ['category' => 'first_time'])
            ->assertNotFound();
    }

    public function test_undoing_a_tap_lowers_the_total_and_removes_the_tap_from_the_log(): void
    {
        $this->tap();
        $this->tap();

        $this->undo()->assertOk()->assertJsonPath('counts.first_time', 1);

        $counter = AttendanceCounter::firstOrFail();
        $this->assertSame(1, $counter->first_time_count);
        $this->assertSame(
            1,
            AttendanceCountEntry::where('category', 'first_time')->count(),
            'The tap log must move with the total, or the count disagrees with its own history.'
        );
    }

    public function test_undo_refuses_to_go_below_zero(): void
    {
        $this->tap();
        $this->undo()->assertOk();

        $this->undo()->assertStatus(422);

        $this->assertSame(0, AttendanceCounter::firstOrFail()->first_time_count);
    }

    public function test_undo_only_touches_the_category_asked_for(): void
    {
        $this->tap('first_time');
        $this->tap('regular');

        $this->undo('first_time')->assertOk();

        $counter = AttendanceCounter::firstOrFail();
        $this->assertSame(0, $counter->first_time_count);
        $this->assertSame(1, $counter->regular_count, 'Undoing one category must not disturb another.');
    }

    public function test_a_correction_sets_the_numbers_and_records_that_it_was_corrected(): void
    {
        $counter = AttendanceCounter::create([
            'group_id' => $this->stream->id,
            'date' => Carbon::today()->toDateString(),
            'first_time_count' => 40,
            'returning_count' => 0,
            'regular_count' => 0,
            'visitor_count' => 0,
        ]);

        AttendanceCounterResource::correct($counter, [
            'first_time_count' => 12,
            'returning_count' => 3,
            'regular_count' => 5,
            'visitor_count' => 1,
            'correction_note' => 'The ushers double-counted the side door.',
        ]);

        $counter->refresh();
        $this->assertSame(12, $counter->first_time_count);
        $this->assertSame(3, $counter->returning_count);
        $this->assertNotNull($counter->corrected_at, 'A corrected total must not look like a counted one.');
        $this->assertSame('The ushers double-counted the side door.', $counter->correction_note);
    }

    public function test_a_correction_leaves_the_tap_history_alone(): void
    {
        $this->tap();
        $this->tap();
        $counter = AttendanceCounter::firstOrFail();

        AttendanceCounterResource::correct($counter, [
            'first_time_count' => 99,
            'returning_count' => 0,
            'regular_count' => 0,
            'visitor_count' => 0,
            'correction_note' => 'Recount after the service.',
        ]);

        $this->assertSame(
            2,
            AttendanceCountEntry::count(),
            'The taps are what actually happened; rewriting them destroys the evidence of the mistake.'
        );
    }
}
