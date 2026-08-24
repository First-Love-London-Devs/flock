<?php

namespace Tests\Feature;

use App\Filament\Resources\MemberResource;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberCsvExport;
use Tests\TestCase;

/**
 * Taking the new converts out of the panel.
 *
 * Belgium asked for this so they can work the list rather than read it, which
 * means the file has to carry the things you follow someone up with: a phone
 * number that survives Excel, a name that survives an accent, and how far
 * through New Believers School they are.
 *
 * The one thing that must never slip is the confinement. The export starts
 * from the same query the table does, so if that ever stops being scoped this
 * hands a country admin the whole of Europe in one click.
 */
class MemberCsvExportTest extends TestCase
{
    private Group $belgium;

    private Group $sweden;

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

        Group::create(['name' => 'Antwerp Centre', 'group_type_id' => $bacentaType->id,
            'parent_id' => $this->belgium->id, 'is_active' => true]);
    }

    private function member(Group $country, array $attributes): Member
    {
        $member = Member::factory()->create($attributes);
        $member->groups()->attach($country->id);

        return $member;
    }

    private function admin(?Group $scope): User
    {
        return User::create([
            'name' => 'Admin '.($scope?->name ?? 'wide'),
            'email' => strtolower($scope?->name ?? 'wide').'@example.test',
            'password' => 'password',
            'scope_group_id' => $scope?->id,
        ]);
    }

    private function csv($source, ?string $type = null): string
    {
        $response = MemberCsvExport::stream($source, MemberCsvExport::filename($type));

        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_the_file_carries_what_you_follow_someone_up_with(): void
    {
        $member = $this->member($this->belgium, [
            'first_name' => 'Chloë', 'last_name' => 'Mateta',
            'phone_number' => '0470123456', 'email' => 'chloe@example.test',
            'member_type' => 'new_convert', 'nbs_status' => 'in_progress',
        ]);

        $csv = $this->csv(Member::whereKey($member->id));

        $this->assertStringContainsString('"First name","Last name",Phone,Email', $csv);
        $this->assertStringContainsString('Chloë', $csv, 'An accented name must survive the file.');
        $this->assertStringContainsString('0470123456', $csv);
        $this->assertStringContainsString('New Convert', $csv, 'Labels, not database keys.');
        $this->assertStringContainsString('In Progress', $csv);
        $this->assertStringContainsString('Belgium', $csv, 'The group is how you know where to send them.');
    }

    public function test_a_leading_zero_on_a_phone_number_is_not_eaten_by_excel(): void
    {
        $member = $this->member($this->belgium, ['phone_number' => '0470123456']);

        $csv = $this->csv(Member::whereKey($member->id));

        // A zero-width space keeps the cell textual; without it Excel shows 470123456.
        $this->assertStringContainsString("\u{200B}0470123456", $csv);
    }

    public function test_the_file_opens_as_utf8_in_excel(): void
    {
        $member = $this->member($this->belgium, ['first_name' => 'Chloë']);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->csv(Member::whereKey($member->id)));
    }

    /** The property that matters: the export cannot reach further than the table. */
    public function test_a_country_admin_cannot_export_another_country(): void
    {
        $this->member($this->belgium, ['first_name' => 'Belgian', 'member_type' => 'new_convert']);
        $this->member($this->sweden, ['first_name' => 'Swede', 'member_type' => 'new_convert']);

        $this->actingAs($this->admin($this->belgium));

        $csv = $this->csv(MemberResource::getEloquentQuery());

        $this->assertStringContainsString('Belgian', $csv);
        $this->assertStringNotContainsString('Swede', $csv, 'Belgium must never export Sweden.');
    }

    public function test_the_group_wide_admin_still_exports_everybody(): void
    {
        $this->member($this->belgium, ['first_name' => 'Belgian']);
        $this->member($this->sweden, ['first_name' => 'Swede']);

        $this->actingAs($this->admin(null));

        $csv = $this->csv(MemberResource::getEloquentQuery());

        $this->assertStringContainsString('Belgian', $csv);
        $this->assertStringContainsString('Swede', $csv);
    }

    public function test_filtering_to_new_converts_exports_only_those(): void
    {
        $this->member($this->belgium, ['first_name' => 'Convert', 'member_type' => 'new_convert']);
        $this->member($this->belgium, ['first_name' => 'Regular', 'member_type' => 'member']);

        $this->actingAs($this->admin($this->belgium));

        // What the table hands the action once the Type filter is set.
        $csv = $this->csv(MemberResource::getEloquentQuery()->where('member_type', 'new_convert'));

        $this->assertStringContainsString('Convert', $csv);
        $this->assertStringNotContainsString('Regular', $csv);
    }

    public function test_the_filename_says_what_the_file_holds(): void
    {
        $this->assertStringStartsWith('new-converts-', MemberCsvExport::filename('new_convert'));
        $this->assertStringStartsWith('members-', MemberCsvExport::filename());
        $this->assertStringEndsWith('.csv', MemberCsvExport::filename('new_convert'));
    }

    public function test_a_ticked_selection_exports_just_those_rows(): void
    {
        $keep = $this->member($this->belgium, ['first_name' => 'Ticked']);
        $this->member($this->belgium, ['first_name' => 'Untouched']);

        $csv = $this->csv(Member::whereKey($keep->id)->get());

        $this->assertStringContainsString('Ticked', $csv);
        $this->assertStringNotContainsString('Untouched', $csv);
    }
}
