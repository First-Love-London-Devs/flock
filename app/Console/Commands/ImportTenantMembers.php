<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Bulk-import members into a tenant from a base64(gzip(json)) payload — the
 * same field mapping + group-by-name attachment as the Filament MemberImporter,
 * but runnable from the CLI so a batch of spreadsheets can be loaded headlessly.
 * The payload is passed inline so no member PII touches the repo or disk.
 *
 *   php artisan flock:import-members go-church --data="<base64-gzip-json>" [--dry]
 *
 * Each row: { first_name, last_name, email, phone_number, date_of_birth,
 *   gender, street_name, postal_code, occupation, member_since,
 *   holy_ghost_baptism, water_baptism, notes, group }
 */
class ImportTenantMembers extends Command
{
    protected $signature = 'flock:import-members {tenant}
        {--data= : base64(gzip(json array))}
        {--match-on=email : How to recognise someone already on the roll: email, or name-dob}
        {--group-type= : Restrict group matching to one type slug, when two tiers share a name}
        {--dry : Report only, write nothing}';

    protected $description = 'Import members into a tenant from an inline payload (group attached by name)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error("Tenant not found: {$this->argument('tenant')}");

            return self::FAILURE;
        }

        $decoded = @gzdecode(base64_decode((string) $this->option('data'), true) ?: '');
        $rows = $decoded ? json_decode($decoded, true) : null;
        if (! is_array($rows)) {
            $this->error('Could not decode --data (expected base64 of gzip of a JSON array).');

            return self::FAILURE;
        }

        $code = self::SUCCESS;
        $tenant->run(function () use ($rows, &$code) {
            $code = $this->importRows($rows, (bool) $this->option('dry'));
        });

        return $code;
    }

    /* protected, not private, so a test can drive it against a tenant database
       directly. handle() needs a central Tenant record, and the test suite
       migrates only the tenant schema. Same arrangement as AddCountryTier. */
    protected function importRows(array $rows, bool $dry): int
    {
        $created = 0;
        $updated = 0;
        $attached = 0;
        $unmatched = [];

        $matchOn = $this->option('match-on') ?: 'email';
        if (! in_array($matchOn, ['email', 'name-dob'], true)) {
            $this->error('--match-on must be email or name-dob.');

            return self::FAILURE;
        }

        $ambiguousUsed = [];

        {
            $norm = fn (?string $s) => preg_replace('/\s+/', ' ', strtolower(str_replace(['’', '`'], "'", trim($s ?? ''))));

            /*
             * Group names are not unique. A church and the stream inside it
             * deliberately share a name, so a flat name => id map silently
             * keeps whichever row the database happened to return last and
             * attaches every member to it. --group-type says which tier is
             * meant; without it, an ambiguous name is refused rather than
             * guessed.
             */
            $typeSlug = $this->option('group-type');
            $type = $typeSlug ? GroupType::where('slug', $typeSlug)->first() : null;
            if ($typeSlug && ! $type) {
                $this->error("No group type with slug \"{$typeSlug}\".");
                $this->line('  Available: '.GroupType::pluck('slug')->implode(', '));

                return self::FAILURE;
            }

            $groupsByNorm = [];
            $ambiguous = [];
            $query = Group::query()->when($type, fn ($q) => $q->where('group_type_id', $type->id));
            foreach ($query->get(['id', 'name']) as $g) {
                $key = $norm($g->name);
                if (isset($groupsByNorm[$key])) {
                    $ambiguous[$key] = true;
                }
                $groupsByNorm[$key] = $g->id;
            }

            foreach ($rows as $r) {
                $email = trim($r['email'] ?? '') ?: null;

                /*
                 * With no email there is nothing to recognise a person by, so
                 * every run created a fresh row and importing the same
                 * spreadsheet twice silently doubled the roll. Sheets that
                 * carry no email address can be matched on name and date of
                 * birth instead, which is weaker than an email but far better
                 * than nothing.
                 */
                if ($email) {
                    $member = Member::firstOrNew(['email' => $email]);
                } elseif ($matchOn === 'name-dob' && ($r['date_of_birth'] ?? null)) {
                    /* whereDate, not a plain equality on the attribute:
                       date_of_birth is cast to a date and stored with a time
                       component, so matching the bare "1997-12-08" from a
                       spreadsheet never found the existing row and every run
                       added the person again. */
                    $member = Member::query()
                        ->where('first_name', $r['first_name'] ?? null)
                        ->where('last_name', $r['last_name'] ?? null)
                        ->whereDate('date_of_birth', $r['date_of_birth'])
                        ->first() ?? new Member();
                } else {
                    $member = new Member();
                }
                $existed = $member->exists;

                $member->fill([
                    'first_name' => $r['first_name'] ?? null,
                    'last_name' => $r['last_name'] ?? null,
                    'email' => $email,
                    'phone_number' => ($r['phone_number'] ?? '') ?: null,
                    'date_of_birth' => ($r['date_of_birth'] ?? '') ?: null,
                    'gender' => ($r['gender'] ?? '') ?: null,
                    'street_name' => ($r['street_name'] ?? '') ?: null,
                    'postal_code' => ($r['postal_code'] ?? '') ?: null,
                    'occupation' => ($r['occupation'] ?? '') ?: null,
                    'member_since' => ($r['member_since'] ?? '') ?: null,
                    'holy_ghost_baptism' => filter_var($r['holy_ghost_baptism'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'water_baptism' => filter_var($r['water_baptism'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'notes' => ($r['notes'] ?? '') ?: null,
                    'member_type' => 'member',
                    'is_active' => true,
                ]);

                if (! $dry) {
                    $member->save();
                }
                $existed ? $updated++ : $created++;

                $groupName = trim($r['group'] ?? '');
                if ($groupName === '') {
                    continue;
                }
                $key = $norm($groupName);
                if (isset($ambiguous[$key])) {
                    $ambiguousUsed[$groupName] = ($ambiguousUsed[$groupName] ?? 0) + 1;

                    continue;
                }
                $groupId = $groupsByNorm[$key] ?? null;
                if (! $groupId) {
                    $unmatched[$groupName] = ($unmatched[$groupName] ?? 0) + 1;

                    continue;
                }
                if (! $dry) {
                    $member->groups()->syncWithoutDetaching([
                        $groupId => [
                            'joined_at' => ($r['member_since'] ?? '') ?: now()->toDateString(),
                            'is_primary' => true,
                        ],
                    ]);
                }
                $attached++;
            }
        }

        $mode = $dry ? '[DRY RUN] ' : '';
        $this->info("{$mode}Created: {$created} | Updated: {$updated} | Group-attached: {$attached}");
        if ($unmatched) {
            $this->warn('Unmatched groups (members left ungrouped): '.json_encode($unmatched, JSON_UNESCAPED_UNICODE));
        }
        if ($ambiguousUsed) {
            $this->error('Ambiguous group names, members left ungrouped: '
                .json_encode($ambiguousUsed, JSON_UNESCAPED_UNICODE));
            $this->line('  More than one group has that name. Pass --group-type=<slug> to say which tier.');
        }

        return self::SUCCESS;
    }
}
