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
