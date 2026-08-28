<?php

namespace Tests\Feature;

use App\Filament\Pages\NewConverts;
use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use App\Models\NonMember;
use App\Models\NonMemberAttendance;
use App\Models\UnderstandingCampaign;
use App\Models\User;
use App\Support\NewConvertsCsvExport;
use App\Support\NewConvertsReport;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The list behind the new-convert count.
 *
 * The count came from flags on attendance records, and those records point at
 * two kinds of person: someone on the roll and someone who is not. In the real
 * tenant that split was 94 to 98, so anything member-only would have quietly
 * halved the list and looked like it worked.
 */
class NewConvertsReportTest extends TestCase
{
    private Group $belgium;

    private Group $sweden;

    private Group $antwerp;

    private Group $stockholm;

    protected function setUp(): void
    {
        parent::setUp();

        $countryType = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $bacentaType = GroupType::create([
            'name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 4,
            'tracks_attendance' => true, 'is_active' => true,
        ]);

        $this->belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->sweden = Group::create(['name' => 'Sweden', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->antwerp = Group::create(['name' => 'Antwerp Centre', 'group_type_id' => $bacentaType->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);
        $this->stockholm = Group::create(['name' => 'Stockholm Centre', 'group_type_id' => $bacentaType->id,
            'parent_id' => $this->sweden->id, 'is_active' => true]);
    }

    /**
     * One summary per group per date, enforced by a unique index. Members and
     * non-members marked on the same Sunday share it, as they do in production.
     */
    private function summary(Group $group, string $date): AttendanceSummary
    {
        // whereDate, because the column is cast to a date and stores midnight,
        // so a plain where against '2026-08-10' never matches what is there.
        return AttendanceSummary::where('group_id', $group->id)->whereDate('date', $date)->first()
            ?? AttendanceSummary::create([
                'group_id' => $group->id, 'date' => $date, 'total_attendance' => 1,
            ]);
    }

    private function markMember(Group $group, string $date, string $name): Member
    {
        $member = Member::factory()->create(['first_name' => $name, 'phone_number' => '0470111222']);
        Attendance::create([
            'attendance_summary_id' => $this->summary($group, $date)->id,
            'member_id' => $member->id, 'attended' => true, 'is_new_convert' => true,
        ]);

        return $member;
    }

    private function markNonMember(Group $group, string $date, string $name): NonMember
    {
        $person = NonMember::create(['first_name' => $name, 'last_name' => 'Visitor', 'group_id' => $group->id]);
        NonMemberAttendance::create([
            'attendance_summary_id' => $this->summary($group, $date)->id,
            'non_member_id' => $person->id, 'attended' => true, 'is_new_convert' => true,
        ]);

        return $person;
    }

    /**
     * Somebody who came in through the public welcome form instead of the
     * register. `re_dedicating` is the question that means a decision for
     * Christ; `first_time` only means they had not been to this church before.
     */
    private function welcomeForm(Group $stream, string $date, string $name, bool $reDedicating = true, bool $firstTime = false): UnderstandingCampaign
    {
        return UnderstandingCampaign::create([
            'stream_id' => $stream->id,
            'attended_on' => $date,
            'first_name' => $name,
            'last_name' => 'Vandenberg',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '2000',
            'phone_number' => '0470999888',
            're_dedicating' => $reDedicating,
            'first_time' => $firstTime,
            'who_invited' => 'A friend',
        ]);
    }

    private function admin(?Group $scope): User
    {
        return User::create([
            'name' => 'Admin', 'email' => strtolower($scope?->name ?? 'wide').'@example.test',
            'password' => 'password', 'scope_group_id' => $scope?->id,
        ]);
    }

    /** The reason this is not a filter on the members table. */
    public function test_it_lists_people_who_are_not_on_the_roll(): void
    {
        $this->markMember($this->antwerp, '2026-08-10', 'OnRoll');
        $this->markNonMember($this->antwerp, '2026-08-10', 'NotOnRoll');

        $rows = NewConvertsReport::rows();

        $this->assertCount(2, $rows, 'A members-only list would silently drop half of them.');
        $this->assertSame(['Member', 'Not on roll'], $rows->pluck('on_roll')->sort()->values()->all());
    }

    public function test_someone_marked_on_three_sundays_is_one_person_to_follow_up(): void
    {
        $member = Member::factory()->create(['first_name' => 'Repeat']);
        foreach (['2026-08-02', '2026-08-09', '2026-08-16'] as $date) {
            Attendance::create([
                'attendance_summary_id' => $this->summary($this->antwerp, $date)->id,
                'member_id' => $member->id, 'attended' => true, 'is_new_convert' => true,
            ]);
        }

        $rows = NewConvertsReport::rows();

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['times_marked']);
        $this->assertSame('2026-08-02', $rows[0]['first_seen']);
        $this->assertSame('2026-08-16', $rows[0]['last_seen']);
    }

    public function test_a_country_admin_only_sees_their_own_country(): void
    {
        $this->markMember($this->antwerp, '2026-08-10', 'Belgian');
        $this->markMember($this->stockholm, '2026-08-10', 'Swede');
        $this->markNonMember($this->stockholm, '2026-08-10', 'SwedishVisitor');

        $this->actingAs($this->admin($this->belgium));

        $names = NewConvertsReport::rows()->pluck('name')->implode(' ');

        $this->assertStringContainsString('Belgian', $names);
        $this->assertStringNotContainsString('Swede', $names, 'Belgium must not see Sweden.');
        $this->assertStringNotContainsString('SwedishVisitor', $names);
    }

    public function test_the_group_wide_admin_sees_every_country(): void
    {
        $this->markMember($this->antwerp, '2026-08-10', 'Belgian');
        $this->markMember($this->stockholm, '2026-08-10', 'Swede');

        $this->actingAs($this->admin(null));

        $this->assertCount(2, NewConvertsReport::rows());
    }

    public function test_the_date_window_is_respected(): void
    {
        $this->markMember($this->antwerp, '2026-01-05', 'Old');
        $this->markMember($this->antwerp, '2026-08-10', 'Recent');

        $names = NewConvertsReport::rows('2026-06-01', '2026-12-31')->pluck('name')->implode(' ');

        $this->assertStringContainsString('Recent', $names);
        $this->assertStringNotContainsString('Old', $names);
    }

    public function test_somebody_not_marked_is_not_in_the_list(): void
    {
        $member = Member::factory()->create(['first_name' => 'JustAttended']);
        Attendance::create([
            'attendance_summary_id' => $this->summary($this->antwerp, '2026-08-10')->id,
            'member_id' => $member->id, 'attended' => true, 'is_new_convert' => false,
        ]);

        $this->assertCount(0, NewConvertsReport::rows());
    }

    /**
     * The reason this source exists at all. Belgium's people fill in the
     * welcome form and nobody ticks the register, so before this the list was
     * empty for them on a Sunday that plainly had converts.
     */
    public function test_the_welcome_form_puts_people_on_the_list(): void
    {
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Amara');

        $rows = NewConvertsReport::rows(null, null);

        $this->assertSame(['Amara Vandenberg'], $rows->pluck('name')->all());
        $this->assertSame('Welcome form', $rows->first()['on_roll']);
        $this->assertSame('2026-08-23', $rows->first()['last_seen']);
    }

    /**
     * ⚠ A first-time visitor is not a convert. The form asks the two things
     * separately and only one of them is a decision for Christ; counting both
     * would inflate a number the leadership reads as conversions.
     */
    public function test_a_first_time_visitor_who_did_not_re_dedicate_is_not_a_convert(): void
    {
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Just Visiting', reDedicating: false, firstTime: true);

        $this->assertCount(0, NewConvertsReport::rows(null, null));
    }

    public function test_the_welcome_form_respects_the_country_an_admin_is_confined_to(): void
    {
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Amara');
        $this->welcomeForm($this->stockholm, '2026-08-23', 'Elsa');

        $this->actingAs($this->admin($this->belgium));

        $this->assertSame(['Amara Vandenberg'], NewConvertsReport::rows(null, null)->pluck('name')->all());
    }

    public function test_the_welcome_form_respects_the_date_window(): void
    {
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Amara');

        $this->assertCount(1, NewConvertsReport::rows('2026-08-23', '2026-08-23'));
        $this->assertCount(0, NewConvertsReport::rows('2026-08-24', '2026-08-31'));
    }

    /** Filling the form on three Sundays is still one person to follow up. */
    public function test_the_same_person_on_the_form_twice_is_one_row(): void
    {
        $this->welcomeForm($this->antwerp, '2026-08-16', 'Amara');
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Amara');

        $rows = NewConvertsReport::rows(null, null);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows->first()['times_marked']);
        $this->assertSame('2026-08-16', $rows->first()['first_seen']);
        $this->assertSame('2026-08-23', $rows->first()['last_seen']);
    }

    /** All three sources appear together, which is the point. */
    public function test_the_register_and_the_welcome_form_appear_in_one_list(): void
    {
        $this->markMember($this->antwerp, '2026-08-23', 'Register');
        $this->welcomeForm($this->antwerp, '2026-08-23', 'Form');

        $this->assertEqualsCanonicalizing(
            ['Welcome form'],
            NewConvertsReport::rows(null, null)->where('name', 'Form Vandenberg')->pluck('on_roll')->all()
        );
        $this->assertCount(2, NewConvertsReport::rows(null, null));
    }

    public function test_the_csv_carries_the_follow_up_columns(): void
    {
        $this->markMember($this->antwerp, '2026-08-10', 'Chloë');

        $response = NewConvertsCsvExport::stream(NewConvertsReport::rows(), 'test.csv');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Excel needs the BOM or accents break.');
        $this->assertStringContainsString('Name,Phone,Email,"On roll",Group', $csv);
        $this->assertStringContainsString('Chloë', $csv);
        $this->assertStringContainsString("\u{200B}0470111222", $csv, 'The leading zero must survive.');
        $this->assertStringContainsString('Antwerp Centre', $csv);
    }

    public function test_a_service_covers_every_group_beneath_it(): void
    {
        // Two bacentas under one service, one under another.
        $stream = GroupType::create(['name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true]);
        $bacenta = GroupType::where('slug', 'bacenta')->firstOrFail();

        $ges = Group::create(['name' => 'Gospel Experience Service', 'group_type_id' => $stream->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);
        $jes = Group::create(['name' => 'Jesus Encounter Service', 'group_type_id' => $stream->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);

        $underGes = Group::create(['name' => 'Ghent', 'group_type_id' => $bacenta->id,
            'parent_id' => $ges->id, 'is_active' => true]);
        $underJes = Group::create(['name' => 'Liege', 'group_type_id' => $bacenta->id,
            'parent_id' => $jes->id, 'is_active' => true]);

        $this->markMember($underGes, '2026-08-10', 'GospelPerson');
        $this->markMember($underJes, '2026-08-10', 'JesusPerson');

        $names = NewConvertsReport::rows(null, null, $ges->id)->pluck('name')->implode(' ');

        $this->assertStringContainsString('GospelPerson', $names, 'A service must reach its bacentas.');
        $this->assertStringNotContainsString('JesusPerson', $names);
    }

    public function test_a_group_filter_narrows_to_that_one_group(): void
    {
        $other = Group::create(['name' => 'Ghent', 'group_type_id' => GroupType::where('slug', 'bacenta')->first()->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);

        $this->markMember($this->antwerp, '2026-08-10', 'Antwerper');
        $this->markMember($other, '2026-08-10', 'Ghenter');

        $names = NewConvertsReport::rows(null, null, null, $this->antwerp->id)->pluck('name')->implode(' ');

        $this->assertStringContainsString('Antwerper', $names);
        $this->assertStringNotContainsString('Ghenter', $names);
    }

    /** A filter must never reach past the admin's own country. */
    public function test_choosing_another_countrys_group_returns_nothing_rather_than_that_country(): void
    {
        $this->markMember($this->stockholm, '2026-08-10', 'Swede');

        $this->actingAs($this->admin($this->belgium));

        $this->assertCount(0, NewConvertsReport::rows(null, null, null, $this->stockholm->id),
            'Naming a group outside your scope must not grant it.');
    }

    public function test_the_filename_says_where_the_export_was_narrowed_to(): void
    {
        $this->assertSame(
            'new-converts-antwerp-centre-2026-01-01-to-2026-08-24.csv',
            NewConvertsCsvExport::filename('2026-01-01', '2026-08-24', 'Antwerp Centre')
        );
    }

    /** A blade error here would never show up in the data tests above. */
    public function test_the_page_renders_with_and_without_rows(): void
    {
        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );
        $this->actingAs($this->admin($this->belgium));

        Livewire::test(NewConverts::class)
            ->assertOk()
            ->assertSee('Nobody was marked as a new convert');

        $this->markMember($this->antwerp, now()->toDateString(), 'Freshly');

        Livewire::test(NewConverts::class)
            ->assertOk()
            ->assertSee('Freshly')
            ->assertSee('Export to CSV');
    }

    public function test_the_filename_carries_the_window(): void
    {
        $this->assertSame('new-converts-2026-01-01-to-2026-08-24.csv',
            NewConvertsCsvExport::filename('2026-01-01', '2026-08-24'));
    }
}
