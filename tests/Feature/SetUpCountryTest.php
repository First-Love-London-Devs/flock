<?php

namespace Tests\Feature;

use App\Console\Commands\SetUpCountry;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The command that creates real logins for a country.
 *
 * It hands out credentials to actual pastors, so the things that matter are:
 * it never resets a password somebody is already using, it never silently
 * guesses which role a leader gets, and running it twice does not produce two
 * of everything.
 */
class SetUpCountryTest extends TestCase
{
    private GroupType $countryType;

    private GroupType $churchType;

    private GroupType $streamType;

    private RoleDefinition $churchRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryType = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $this->churchType = GroupType::create([
            'name' => 'Gathering Service', 'slug' => 'gathering-service', 'level' => 1,
            'tracks_attendance' => true, 'is_active' => true,
        ]);

        $this->streamType = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true,
        ]);

        $this->churchRole = RoleDefinition::create([
            'name' => 'Church Leader', 'slug' => 'church-leader', 'permission_level' => 50,
            'applies_to_group_type_id' => $this->churchType->id, 'is_active' => true,
        ]);
        // A second role that applies to something else, so a correct
        // implementation must not simply take the first row it finds.
        RoleDefinition::create([
            'name' => 'Super Admin', 'slug' => 'super-admin', 'permission_level' => 100,
            'applies_to_group_type_id' => null, 'is_active' => true,
        ]);
    }

    private function seedSwitzerland(array $churches = ['Geneva', 'Basel']): Group
    {
        $country = Group::create([
            'name' => 'Switzerland', 'group_type_id' => $this->countryType->id,
            'parent_id' => null, 'is_active' => true,
        ]);

        foreach ($churches as $name) {
            Group::create([
                'name' => $name, 'group_type_id' => $this->churchType->id,
                'parent_id' => $country->id, 'is_active' => true,
            ]);
        }

        return $country;
    }

    /** Drive setUp() directly; handle() needs a central Tenant record. */
    /* Not run(): PHPUnit\Framework\TestCase::run() is final. */
    private function execute(bool $dry = false, array $options = []): string
    {
        $output = new BufferedOutput;

        $command = new class extends SetUpCountry
        {
            public array $opts = [];

            public array $args = [];

            public function callSetUp(bool $dry): int
            {
                return $this->setUp($dry);
            }

            public function option($key = null)
            {
                return $this->opts[$key] ?? null;
            }

            public function argument($key = null)
            {
                return $this->args[$key] ?? null;
            }
        };

        $command->opts = $options;
        $command->args = ['tenant' => 'go-church', 'country' => 'Switzerland'];
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $command->callSetUp($dry);

        return $output->fetch();
    }

    public function test_it_creates_a_scoped_panel_admin_and_a_leader_for_every_church(): void
    {
        $country = $this->seedSwitzerland();

        $this->execute();

        $admin = User::where('email', 'switzerland@go-church.flock')->first();
        $this->assertNotNull($admin, 'The panel admin should exist.');
        $this->assertSame($country->id, $admin->scope_group_id, 'The admin must be confined to Switzerland.');

        foreach (['Geneva', 'Basel'] as $name) {
            $church = Group::where('name', $name)->where('parent_id', $country->id)->firstOrFail();
            $stream = Group::where('parent_id', $church->id)
                ->where('group_type_id', $this->streamType->id)->first();
            $this->assertNotNull($stream, "{$name} should have a stream beneath it.");
            $this->assertSame($name, $stream->name, 'The stream carries the church name.');
        }

        $this->assertSame(2, Leader::count(), 'One leader per church.');

        foreach (['geneva', 'basel'] as $slug) {
            $leader = Leader::where('username', $slug.'@flock.local')->first();
            $this->assertNotNull($leader, "{$slug} should have a login.");

            $role = LeaderRole::where('leader_id', $leader->id)->first();
            $this->assertNotNull($role, "{$slug} should lead something.");
            $this->assertSame($this->churchRole->id, $role->role_definition_id);
            $this->assertTrue($role->is_active);
        }
    }

    public function test_passwords_are_hashed_not_stored_as_typed(): void
    {
        $this->seedSwitzerland(['Geneva']);

        $output = $this->execute();

        $leader = Leader::where('username', 'geneva@flock.local')->firstOrFail();

        // The password is printed once for handover, so pull it back out of
        // the table and prove the stored value is a hash of it, not it.
        preg_match('/geneva@flock\.local\s*\|\s*(\S+)/', $output, $m);
        $this->assertNotEmpty($m[1] ?? null, 'The password should be printed for handover.');
        $this->assertNotSame($m[1], $leader->password, 'The password must not be stored as typed.');
        $this->assertTrue(Hash::check($m[1], $leader->password), 'The stored hash must match what was printed.');
    }

    public function test_running_twice_changes_nothing_and_never_resets_a_password(): void
    {
        $this->seedSwitzerland();

        $this->execute();
        $before = Leader::where('username', 'geneva@flock.local')->firstOrFail()->password;

        $second = $this->execute();

        $this->assertSame(2, Leader::count(), 'A second run must not duplicate leaders.');
        $this->assertSame(1, User::where('email', 'switzerland@go-church.flock')->count());
        $this->assertSame(2, LeaderRole::count(), 'A second run must not duplicate roles.');
        $this->assertStringContainsString('exists, left alone', $second);

        $after = Leader::where('username', 'geneva@flock.local')->firstOrFail()->password;
        $this->assertSame($before, $after, 'Re-running must never reset a live password.');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seedSwitzerland();

        $output = $this->execute(dry: true);

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertSame(0, Leader::count());
        $this->assertSame(0, LeaderRole::count());
        $this->assertSame(0, User::where('email', 'switzerland@go-church.flock')->count());
    }

    public function test_it_finishes_a_leader_left_without_a_role(): void
    {
        $country = $this->seedSwitzerland(['Geneva']);
        $church = Group::where('parent_id', $country->id)->firstOrFail();

        // A previous run that died between creating the login and the role.
        $member = \App\Models\Member::create([
            'first_name' => 'Geneva', 'last_name' => 'Leader',
            'email' => 'geneva@flock.local', 'member_type' => 'registered', 'is_active' => true,
        ]);
        $orphan = Leader::create([
            'member_id' => $member->id, 'username' => 'geneva@flock.local',
            'password' => 'existing-password', 'is_active' => true,
        ]);

        $output = $this->execute();

        $this->assertStringContainsString('leads nothing, adding role', $output);
        $this->assertSame(1, Leader::count(), 'It must not create a second login.');
        $this->assertSame(
            1,
            LeaderRole::where('leader_id', $orphan->id)->where('group_id', $church->id)->count(),
            'The existing login should now lead its church.'
        );
    }

    /**
     * A fresh country has no role that applies to its churches, which is the
     * real situation in every country except Belgium. The structure and the
     * admin must still be built: failing the whole run would leave the country
     * exactly as empty as before.
     */
    public function test_it_builds_the_structure_and_skips_leaders_when_no_role_applies(): void
    {
        $country = $this->seedSwitzerland(['Geneva']);
        // Nothing applies to a gathering service, as in production.
        RoleDefinition::where('applies_to_group_type_id', $this->churchType->id)->delete();

        $output = $this->execute();

        $church = Group::where('name', 'Geneva')->where('parent_id', $country->id)->firstOrFail();
        $this->assertSame(
            1,
            Group::where('parent_id', $church->id)->where('group_type_id', $this->streamType->id)->count(),
            'The stream should still have been created.'
        );
        $this->assertSame(1, User::where('email', 'switzerland@go-church.flock')->count(), 'The admin too.');

        $this->assertStringContainsString('No leaders created', $output);
        $this->assertSame(0, Leader::count(), 'No leader may be invented without a role.');
    }

    public function test_it_names_an_explicitly_requested_role_it_cannot_find(): void
    {
        $this->seedSwitzerland(['Geneva']);

        $output = $this->execute(options: ['leader-role' => 'no-such-role']);

        $this->assertStringContainsString('No role definition with slug', $output);
        $this->assertSame(0, Leader::count());
    }

    public function test_running_twice_does_not_duplicate_the_stream(): void
    {
        $country = $this->seedSwitzerland(['Geneva']);

        $this->execute();
        $second = $this->execute();

        $church = Group::where('name', 'Geneva')->where('parent_id', $country->id)->firstOrFail();
        $this->assertSame(
            1,
            Group::where('parent_id', $church->id)->where('group_type_id', $this->streamType->id)->count(),
            'A second run must not add a second stream.'
        );
        $this->assertStringContainsString('stream exists', $second);
    }

    public function test_it_stops_when_the_country_does_not_exist(): void
    {
        $output = $this->execute();

        $this->assertStringContainsString('No group named "Switzerland"', $output);
        $this->assertSame(0, Leader::count());
    }
}
