<?php

namespace App\Console\Commands;

use App\Models\Group;
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
    protected $signature = 'flock:import-members {tenant} {--data= : base64(gzip(json array))} {--dry : Report only, write nothing}';

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

        $dry = (bool) $this->option('dry');
        $created = 0;
        $updated = 0;
        $attached = 0;
        $unmatched = [];

        $tenant->run(function () use ($rows, $dry, &$created, &$updated, &$attached, &$unmatched) {
            $norm = fn (?string $s) => preg_replace('/\s+/', ' ', strtolower(str_replace(['’', '`'], "'", trim($s ?? ''))));

            $groupsByNorm = [];
            foreach (Group::get(['id', 'name']) as $g) {
                $groupsByNorm[$norm($g->name)] = $g->id;
            }

            foreach ($rows as $r) {
                $email = trim($r['email'] ?? '') ?: null;
                $member = $email ? Member::firstOrNew(['email' => $email]) : new Member();
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
                $groupId = $groupsByNorm[$norm($groupName)] ?? null;
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
        });

        $mode = $dry ? '[DRY RUN] ' : '';
        $this->info("{$mode}Created: {$created} | Updated: {$updated} | Group-attached: {$attached}");
        if ($unmatched) {
            $this->warn('Unmatched groups (members left ungrouped): '.json_encode($unmatched, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
