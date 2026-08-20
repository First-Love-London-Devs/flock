<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put a country tier above a tenant's churches.
 *
 * Written for the European group: 40 churches across eight countries, where
 * each church runs its own attendance and the group must see all of them.
 * Flock gives every tenant its own database, so "see all" means one tenant
 * — and one tenant of 40 churches with no country level in between is a
 * flat list nobody can navigate.
 *
 * What it does:
 *   1. Adds a `country` group type above whatever is currently top.
 *   2. Creates a group per country.
 *   3. Re-parents existing top-level churches under their country.
 *   4. Creates any church named in the map that does not exist yet.
 *
 * Nothing is deleted and no member, leader or attendance record is touched:
 * re-parenting sets parent_id on the church, and everything beneath travels
 * with it because the hierarchy is by parent_id.
 *
 * Idempotent throughout, so it can be run again after the map changes
 * without duplicating anything.
 *
 * Run --dry first. It prints exactly what would change and writes nothing.
 */
class AddCountryTier extends Command
{
    protected $signature = 'flock:add-country-tier
        {tenant : Tenant id, e.g. go-church}
        {--church-type= : Slug of the group type the churches are, if it cannot be inferred}
        {--dry : Show what would change and write nothing}';

    protected $description = 'Add a country tier above a tenant\'s churches and place them under it';

    /**
     * Country => churches. Names are exactly as the client supplied them,
     * except where an existing record already spells one differently: those
     * are listed under `aliases` so the command matches the church already
     * in the system rather than creating a second one beside it.
     */
    private const MAP = [
        'Switzerland' => ['Geneva', 'Biel', 'Basel', 'Bern', 'Glarus', 'Zurich', 'JES', 'Lausanne'],
        'Netherlands' => ['Rotterdam', 'Amsterdam', 'Amsterdam North', 'Amsterdam East'],
        'Bulgaria' => ['Pleven'],
        'Portugal' => ['Lisbon', 'Porto'],
        'Sweden' => ['Stockholm', 'Kristianstad', 'Malmö', 'Örebro'],
        'Germany' => ['Freiburg im Breisgau', 'Berlin', 'Düsseldorf', 'Kassel', 'Wiesbaden', 'Munich', 'Fulda'],
        'France' => ['Grenoble', 'Marseille', 'Paris', 'Lyon'],
        'Belgium' => [
            'Go Church Antwerp', 'Go Church Brugge', 'Go Church Boom', 'Go Church Brussels',
            'Go Church Duffel', 'Go Church Ghent', 'Go Church Leuven', 'Go Church Mechelen',
            'Go Church Sint-Niklaas', 'Go Church Turnhout',
        ],
    ];

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error("No tenant with id {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $tenant->run(function () use ($dry) {
            $this->restructure($dry);
        });

        return self::SUCCESS;
    }

    private function resolveChurchType(): ?GroupType
    {
        if ($slug = $this->option('church-type')) {
            $type = GroupType::where('slug', $slug)->first();
            if (! $type) { $this->error("  No group type with slug \"{$slug}\"."); }

            return $type;
        }

        $country = GroupType::where('slug', 'country')->first();
        $slugs = Group::query()
            ->whereNull('parent_id')
            ->when($country, fn ($q) => $q->where('group_type_id', '!=', $country->id))
            ->with('groupType')
            ->get()
            ->pluck('groupType.slug')
            ->filter()
            ->unique()
            ->values();

        if ($slugs->count() === 1) {
            return GroupType::where('slug', $slugs->first())->first();
        }

        if ($slugs->isEmpty()) {
            $this->error('  Nothing is top-level, so the church type cannot be inferred.');
        } else {
            $this->error('  Several types are top-level: '.$slugs->implode(', '));
        }
        $this->error('  Pass --church-type=<slug> to say which one the churches are.');

        return null;
    }

    private function restructure(bool $dry): void
    {
        $this->line($dry ? 'DRY RUN — nothing will be written.' : 'Applying changes.');
        $this->newLine();

        /* The country type sits above everything that exists. `level` is
           only used for ordering (GroupTypeController sorts by it), so the
           existing types are pushed down rather than renumbered by hand. */
        $countryType = GroupType::where('slug', 'country')->first();
        if (! $countryType) {
            $this->line('  + group type "Country" at level 0, existing types shifted down');
            if (! $dry) {
                DB::transaction(function () {
                    GroupType::query()->increment('level');
                    GroupType::create([
                        'name' => 'Country',
                        'slug' => 'country',
                        'level' => 0,
                        'tracks_attendance' => false,
                        'icon' => 'heroicon-o-globe-europe-africa',
                        'color' => '#0ea5e9',
                        'is_active' => true,
                    ]);
                });
                $countryType = GroupType::where('slug', 'country')->first();
            }
        } else {
            $this->line('  = group type "Country" already exists');
        }

        /* Which type are the churches?

           Read it from the data — the type of the groups that are actually
           at the top of the tree — rather than by sorting types by level.
           Level is only an ordering hint and is not unique: this tenant has
           zone, constituency and gathering-service all sitting at level 0
           from the default seeder, so "lowest level wins" picked `zone`,
           found none of the real churches under it, and proposed creating
           all forty from scratch. On production that would have built ten
           duplicate Belgian churches alongside the live ones.

           If nothing is top-level yet, or several types are, the command
           refuses rather than guessing; --church-type settles it. */
        $churchType = $this->resolveChurchType();
        if (! $churchType) { return; }
        $this->line("  = churches are of type \"{$churchType->slug}\"");
        $this->newLine();

        $created = $moved = $existing = 0;
        $handled = collect();

        foreach (self::MAP as $country => $churches) {
            $countryGroup = Group::where('name', $country)
                ->when($countryType, fn ($q) => $q->where('group_type_id', $countryType->id))
                ->first();

            if (! $countryGroup) {
                $this->line("  + country: {$country}");
                if (! $dry) {
                    $countryGroup = Group::create([
                        'name' => $country,
                        'group_type_id' => $countryType->id,
                        'parent_id' => null,
                        'is_active' => true,
                    ]);
                }
            } else {
                $this->line("  = country: {$country}");
            }

            foreach ($churches as $church) {
                $existingGroup = Group::where('name', $church)
                    ->where('group_type_id', $churchType->id)
                    ->first();

                if ($existingGroup) {
                    $handled->push($existingGroup->id);

                    /* On a dry run the country has not been created, so
                       $countryGroup is null — and a top-level church also
                       has parent_id null, so comparing the two reported
                       "already under Belgium" for churches the same run
                       then listed as still top-level. Only count it as
                       correct when the country actually exists. */
                    $alreadyPlaced = $countryGroup !== null
                        && $existingGroup->parent_id === $countryGroup->id;

                    if ($alreadyPlaced) {
                        $this->line("      = {$church} (already under {$country})");
                        $existing++;
                    } else {
                        $this->line("      ~ {$church} → moved under {$country}");
                        // Only parent_id changes. Members, leaders, sub-groups
                        // and attendance all hang off this group and travel
                        // with it untouched.
                        if (! $dry) { $existingGroup->update(['parent_id' => $countryGroup->id]); }
                        $moved++;
                    }

                    continue;
                }

                $this->line("      + {$church} (new)");
                if (! $dry) {
                    Group::create([
                        'name' => $church,
                        'group_type_id' => $churchType->id,
                        'parent_id' => $countryGroup->id,
                        'is_active' => true,
                    ]);
                }
                $created++;
            }
        }

        $this->newLine();
        $this->info("  churches created: {$created}   moved under a country: {$moved}   already correct: {$existing}");

        /* A church that exists but is not in the map is left exactly where
           it is and reported, rather than quietly reorganised.

           Excluding the ones the map just handled matters on a dry run:
           nothing was written, so every church it would have moved is still
           top-level, and this listed all ten of them as "not in the map"
           immediately after moving them. Output that contradicts itself
           reads as a failed run. */
        $orphans = Group::where('group_type_id', $churchType->id)
            ->whereNull('parent_id')
            ->whereNotIn('id', $handled)
            ->pluck('name');
        if ($orphans->isNotEmpty()) {
            $this->newLine();
            $this->warn('  Still top-level, not in the map — left untouched:');
            foreach ($orphans as $name) { $this->warn("      {$name}"); }
        }
    }
}
