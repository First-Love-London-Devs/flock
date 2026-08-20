<?php

namespace Tests\Feature;

use App\Console\Commands\AddCountryTier;
use App\Models\Group;
use App\Models\GroupType;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The command that restructures a live tenant's church tree.
 *
 * It is about to re-parent forty churches belonging to a real client, and
 * it has already had one bug that would have created ten duplicate Belgian
 * churches alongside the live ones. These tests exist so the next change to
 * it cannot do that quietly.
 */
class AddCountryTierTest extends TestCase
{
    private GroupType $gatheringService;

    protected function setUp(): void
    {
        parent::setUp();

        /* Reproduces the shape that caused the duplicate-churches bug: three
           types all sitting at level 0, so anything that picks the church
           type by sorting on `level` gets an arbitrary answer. */
        GroupType::create(['name' => 'Zone', 'slug' => 'zone', 'level' => 0, 'tracks_attendance' => false, 'is_active' => true]);
        GroupType::create(['name' => 'Constituency', 'slug' => 'constituency', 'level' => 0, 'tracks_attendance' => false, 'is_active' => true]);
        $this->gatheringService = GroupType::create([
            'name' => 'Gathering Service', 'slug' => 'gathering-service',
            'level' => 0, 'tracks_attendance' => true, 'is_active' => true,
        ]);
    }

    /** The ten Belgian churches, top-level, exactly as production has them. */
    private function seedBelgianChurches(): void
    {
        foreach ([
            'Go Church Antwerp', 'Go Church Brugge', 'Go Church Boom', 'Go Church Brussels',
            'Go Church Duffel', 'Go Church Ghent', 'Go Church Leuven', 'Go Church Mechelen',
            'Go Church Sint-Niklaas', 'Go Church Turnhout',
        ] as $name) {
            Group::create([
                'name' => $name,
                'group_type_id' => $this->gatheringService->id,
                'parent_id' => null,
                'is_active' => true,
            ]);
        }
    }

    /** Drive restructure() directly; handle() needs a central Tenant record. */
    private function restructure(bool $dry = false): string
    {
        $output = new BufferedOutput;

        $command = new class extends AddCountryTier
        {
            public function callRestructure(bool $dry): void
            {
                $this->restructure($dry);
            }

            // No console input is bound, so the option lookup must not explode.
            public function option($key = null)
            {
                return null;
            }
        };

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $command->callRestructure($dry);

        return $output->fetch();
    }

    public function test_it_places_existing_churches_under_their_country_without_duplicating_them(): void
    {
        $this->seedBelgianChurches();

        $this->restructure();

        $belgium = Group::where('name', 'Belgium')->first();
        $this->assertNotNull($belgium, 'Belgium should have been created.');
        $this->assertSame('country', $belgium->groupType->slug);
        $this->assertNull($belgium->parent_id, 'A country sits at the top.');

        $antwerp = Group::where('name', 'Go Church Antwerp')->get();
        $this->assertCount(1, $antwerp, 'The existing church must be moved, never duplicated.');
        $this->assertSame($belgium->id, $antwerp->first()->parent_id);

        // Ten existing plus thirty from the map.
        $this->assertSame(40, Group::where('group_type_id', $this->gatheringService->id)->count());
        $this->assertSame(8, Group::whereHas('groupType', fn ($q) => $q->where('slug', 'country'))->count());
    }

    public function test_the_church_type_is_read_from_the_tree_not_guessed_from_level(): void
    {
        $this->seedBelgianChurches();

        $output = $this->restructure();

        $this->assertStringContainsString('churches are of type "gathering-service"', $output);

        // The trap: zone and constituency also sit at level 0. Nothing should
        // have been created under either of them.
        foreach (['zone', 'constituency'] as $slug) {
            $type = GroupType::where('slug', $slug)->first();
            $this->assertSame(
                0,
                Group::where('group_type_id', $type->id)->count(),
                "Nothing should have been created as a {$slug}.",
            );
        }
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->seedBelgianChurches();

        $this->restructure();
        $before = Group::count();

        $output = $this->restructure();

        $this->assertSame($before, Group::count(), 'A second run must not create anything.');
        $this->assertStringContainsString('churches created: 0', $output);
        $this->assertStringContainsString('already correct: 40', $output);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->seedBelgianChurches();

        $output = $this->restructure(dry: true);

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertSame(10, Group::count(), 'A dry run must leave the tree alone.');
        $this->assertFalse(GroupType::where('slug', 'country')->exists());
    }

    /* The dry-run report used to name every church it had just said it was
       moving as "still top-level, not in the map", because nothing had been
       written yet so the trailing check found them all again. Output that
       contradicts itself reads as a failed run. */
    public function test_a_dry_run_does_not_report_churches_it_just_placed_as_orphans(): void
    {
        $this->seedBelgianChurches();

        $output = $this->restructure(dry: true);

        $this->assertStringContainsString('moved under Belgium', $output);
        $this->assertStringNotContainsString('Still top-level', $output);
    }

    public function test_a_church_outside_the_map_is_left_alone_and_reported(): void
    {
        $this->seedBelgianChurches();
        Group::create([
            'name' => 'Go Church Somewhere Else',
            'group_type_id' => $this->gatheringService->id,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $output = $this->restructure();

        $stray = Group::where('name', 'Go Church Somewhere Else')->first();
        $this->assertNull($stray->parent_id, 'An unmapped church must not be quietly reorganised.');
        $this->assertStringContainsString('Still top-level', $output);
        $this->assertStringContainsString('Go Church Somewhere Else', $output);
    }
}
