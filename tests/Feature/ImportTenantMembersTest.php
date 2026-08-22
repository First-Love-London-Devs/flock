<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupType;
use App\Console\Commands\ImportTenantMembers;
use App\Models\Member;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The bulk member importer.
 *
 * It loads real people from a client's spreadsheet, so the two ways it can
 * quietly ruin a roll are what these cover: importing the same sheet twice and
 * doubling everyone, and attaching everyone to the wrong group because two
 * tiers share a name.
 */
class ImportTenantMembersTest extends TestCase
{
    private GroupType $churchType;

    private GroupType $streamType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->churchType = GroupType::create([
            'name' => 'Gathering Service', 'slug' => 'gathering-service', 'level' => 1,
            'tracks_attendance' => true, 'is_active' => true,
        ]);
        $this->streamType = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
    }

    /** The real shape: a church, and a stream inside it with the same name. */
    private function seedZurich(): array
    {
        $church = Group::create([
            'name' => 'Zurich', 'group_type_id' => $this->churchType->id,
            'parent_id' => null, 'is_active' => true,
        ]);
        $stream = Group::create([
            'name' => 'Zurich', 'group_type_id' => $this->streamType->id,
            'parent_id' => $church->id, 'is_active' => true,
        ]);

        return [$church, $stream];
    }

    /* Drives importRows() directly: handle() needs a central Tenant record and
       the suite migrates only the tenant schema. */
    private function import(array $rows, array $options = []): string
    {
        $output = new BufferedOutput;

        $command = new class extends ImportTenantMembers
        {
            public array $opts = [];

            public function callImport(array $rows, bool $dry): int
            {
                return $this->importRows($rows, $dry);
            }

            public function option($key = null)
            {
                return $this->opts[$key] ?? null;
            }
        };

        $command->opts = $options;
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $command->callImport($rows, (bool) ($options['dry'] ?? false));

        return $output->fetch();
    }

    /** A sheet with no email column, which is what the client actually sent. */
    private function emaillessRows(): array
    {
        return [
            ['first_name' => 'Aaron', 'last_name' => 'Kokodoko', 'date_of_birth' => '1997-12-08'],
            ['first_name' => 'Aicha', 'last_name' => 'Bennour', 'date_of_birth' => '2006-05-23'],
        ];
    }

    public function test_without_an_email_the_same_sheet_imported_twice_doubles_the_roll(): void
    {
        // Documents the default. It is why --match-on=name-dob exists.
        $this->import($this->emaillessRows());
        $this->import($this->emaillessRows());

        $this->assertSame(4, Member::count(), 'Two runs of two rows create four members.');
    }

    public function test_matching_on_name_and_date_of_birth_makes_a_second_run_harmless(): void
    {
        $this->import($this->emaillessRows(), ['match-on' => 'name-dob']);
        $this->import($this->emaillessRows(), ['match-on' => 'name-dob']);

        $this->assertSame(2, Member::count(), 'The same people must not be added twice.');
    }

    public function test_an_ambiguous_group_name_leaves_members_ungrouped_rather_than_guessing(): void
    {
        $this->seedZurich();

        $output = $this->import([
            ['first_name' => 'Aaron', 'last_name' => 'Kokodoko', 'date_of_birth' => '1997-12-08', 'group' => 'Zurich'],
        ]);

        $this->assertStringContainsString('Ambiguous group names', $output);
        $member = Member::firstOrFail();
        $this->assertCount(0, $member->groups, 'Better ungrouped than attached to the wrong tier.');
    }

    public function test_group_type_picks_the_tier_when_two_share_a_name(): void
    {
        [$church, $stream] = $this->seedZurich();

        $this->import([
            ['first_name' => 'Aaron', 'last_name' => 'Kokodoko', 'date_of_birth' => '1997-12-08', 'group' => 'Zurich'],
        ], ['group-type' => 'stream']);

        $member = Member::firstOrFail();
        $this->assertSame([$stream->id], $member->groups->pluck('id')->all(), 'Should attach to the stream.');
        $this->assertNotContains($church->id, $member->groups->pluck('id')->all());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seedZurich();

        $output = $this->import($this->emaillessRows(), ['dry' => true, 'group-type' => 'stream']);

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertSame(0, Member::count());
    }

    public function test_it_rejects_an_unknown_group_type_rather_than_importing_ungrouped(): void
    {
        $this->seedZurich();

        $output = $this->import($this->emaillessRows(), ['group-type' => 'nonsense']);

        $this->assertStringContainsString('No group type with slug', $output);
        $this->assertSame(0, Member::count(), 'Nothing should be written on a bad option.');
    }
}
