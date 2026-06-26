<?php

namespace Tests\Feature\Imports;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use App\Services\MemberImportSanityChecker;
use Tests\TestCase;

class MemberImportSanityCheckerTest extends TestCase
{
    private const HEADERS = ['first_name', 'last_name', 'email', 'phone_number', 'gender', 'group'];

    private function check(array $rows): array
    {
        return app(MemberImportSanityChecker::class)->check($rows, self::HEADERS);
    }

    public function test_it_resolves_matched_groups_to_their_gathering_service(): void
    {
        $gsType = GroupType::factory()->create(['name' => 'Gathering Service', 'slug' => 'gathering-service']);
        $bacentaType = GroupType::factory()->create(['name' => 'Bacenta', 'slug' => 'bacenta']);

        $gs = Group::factory()->create(['name' => 'Go Church Leuven', 'group_type_id' => $gsType->id]);
        Group::factory()->create(['name' => 'Tienen', 'group_type_id' => $bacentaType->id, 'parent_id' => $gs->id]);

        $report = $this->check([
            ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => '', 'phone_number' => '0470', 'gender' => 'female', 'group' => 'Tienen'],
        ]);

        $matched = collect($report['groups'])->firstWhere('name', 'Tienen');
        $this->assertTrue($matched['matched']);
        $this->assertSame('Go Church Leuven', $matched['gs']);
        $this->assertSame(1, $matched['count']);
        $this->assertCount(0, $report['unmatchedGroups']);
    }

    public function test_it_flags_unmatched_groups(): void
    {
        $report = $this->check([
            ['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => '', 'phone_number' => '', 'gender' => '', 'group' => 'Steps To God’s Presence - Nona'],
        ]);

        $this->assertCount(1, $report['unmatchedGroups']);
        $this->assertSame('Steps To God’s Presence - Nona', $report['unmatchedGroups'][0]['name']);
    }

    public function test_it_detects_duplicate_emails_in_the_file(): void
    {
        $report = $this->check([
            ['first_name' => 'Denzel', 'last_name' => 'Viyoff', 'email' => 'shared@example.com', 'phone_number' => '1', 'gender' => '', 'group' => ''],
            ['first_name' => 'Shawn', 'last_name' => 'Viyoff', 'email' => 'Shared@example.com', 'phone_number' => '2', 'gender' => '', 'group' => ''],
        ]);

        $this->assertArrayHasKey('shared@example.com', $report['dupEmailsInFile']);
        $this->assertSame([2, 3], $report['dupEmailsInFile']['shared@example.com']);
    }

    public function test_it_counts_create_versus_update_using_case_insensitive_email(): void
    {
        Member::factory()->create(['email' => 'existing@example.com']);

        $report = $this->check([
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'EXISTING@example.com', 'phone_number' => '', 'gender' => '', 'group' => ''],
            ['first_name' => 'C', 'last_name' => 'D', 'email' => 'brand.new@example.com', 'phone_number' => '', 'gender' => '', 'group' => ''],
        ]);

        $this->assertSame(1, $report['willUpdate']);
        $this->assertSame(1, $report['willCreate']);
    }

    public function test_it_flags_invalid_emails_missing_names_and_bad_enums(): void
    {
        $report = $this->check([
            ['first_name' => '', 'last_name' => 'NoFirst', 'email' => 'not-an-email', 'phone_number' => '', 'gender' => 'martian', 'group' => ''],
        ]);

        $this->assertSame([2], $report['missingName']);
        $this->assertCount(1, $report['invalidEmail']);
        $this->assertSame('not-an-email', $report['invalidEmail'][0]['value']);
        $this->assertCount(1, $report['invalidEnum']);
        $this->assertSame('gender', $report['invalidEnum'][0]['field']);
    }
}
