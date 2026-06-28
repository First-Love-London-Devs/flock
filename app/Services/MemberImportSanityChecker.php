<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Dry-run validator for a member import CSV.
 *
 * Mirrors what App\Filament\Imports\MemberImporter does at import time — but
 * touches NOTHING in the database. It reports what would happen so an admin can
 * fix the source sheet before committing the real import.
 */
class MemberImportSanityChecker
{
    /** The columns MemberImporter understands (anything else is ignored on import). */
    public const EXPECTED_HEADERS = [
        'first_name', 'last_name', 'email', 'phone_number', 'date_of_birth',
        'gender', 'address', 'occupation', 'marital_status', 'nbs_status',
        'holy_ghost_baptism', 'water_baptism', 'member_type', 'member_since',
        'notes', 'group',
    ];

    /**
     * @param  array<int, array<string, string>>  $rows     header-mapped rows
     * @param  array<int, string>                 $headers  lower-cased headers present in the file
     * @return array<string, mixed>
     */
    public function check(array $rows, array $headers): array
    {
        $report = [
            'total' => count($rows),
            'missingRequiredHeaders' => [],
            'unknownHeaders' => [],
            'missingName' => [],
            'invalidEmail' => [],
            'invalidEnum' => [],
            'dupEmailsInFile' => [],
            'blankEmail' => 0,
            'blankMatchExisting' => 0,
            'willCreate' => 0,
            'willUpdate' => 0,
            'willRestore' => 0,
            'groups' => [],
            'unmatchedGroups' => [],
        ];

        foreach (['first_name', 'last_name'] as $required) {
            if (! in_array($required, $headers, true)) {
                $report['missingRequiredHeaders'][] = $required;
            }
        }
        foreach ($headers as $header) {
            if ($header !== '' && ! in_array($header, self::EXPECTED_HEADERS, true)) {
                $report['unknownHeaders'][] = $header;
            }
        }

        [$existingEmails, $existingName, $existingNamePhone] = $this->loadExistingMembers();

        $genders = array_keys(Member::GENDERS);
        $memberTypes = array_keys(Member::MEMBER_TYPES);
        $nbsStatuses = array_keys(Member::NBS_STATUSES);

        $emailLines = [];
        $groupCounts = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 for the header, +1 to make it 1-indexed

            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            if ($first === '' || $last === '') {
                $report['missingName'][] = $line;
            }

            $emailRaw = trim((string) ($row['email'] ?? ''));
            $email = mb_strtolower($emailRaw);
            if ($emailRaw === '') {
                $report['blankEmail']++;
            } else {
                $emailLines[$email][] = $line;
                if (! filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                    $report['invalidEmail'][] = ['line' => $line, 'value' => $emailRaw];
                }
            }

            // Will this row create a new member or update an existing one? Mirror
            // the importer: match on email (case-insensitive); when blank, fall
            // back to name (+ phone). A blank-email row that matches by name is
            // the re-import duplication risk worth surfacing.
            $nameKey = mb_strtolower($first) . '|' . mb_strtolower($last);
            $phone = preg_replace('/\D+/', '', (string) ($row['phone_number'] ?? ''));
            if ($email !== '') {
                if (isset($existingEmails[$email])) {
                    $report['willUpdate']++;
                    if ($existingEmails[$email]) {
                        $report['willRestore']++;
                    }
                } else {
                    $report['willCreate']++;
                }
            } else {
                $matchTrashed = null;
                if ($phone !== '' && isset($existingNamePhone[$nameKey . '|' . $phone])) {
                    $matchTrashed = $existingNamePhone[$nameKey . '|' . $phone];
                } elseif (isset($existingName[$nameKey])) {
                    $matchTrashed = $existingName[$nameKey];
                }
                if ($matchTrashed !== null) {
                    $report['willUpdate']++;
                    $report['blankMatchExisting']++;
                    if ($matchTrashed) {
                        $report['willRestore']++;
                    }
                } else {
                    $report['willCreate']++;
                }
            }

            foreach ([['gender', $genders], ['member_type', $memberTypes], ['nbs_status', $nbsStatuses]] as [$field, $allowed]) {
                $value = trim((string) ($row[$field] ?? ''));
                // Mirror the importer: it normalises "Female" → female before
                // validating, so only genuinely unknown values are flagged.
                if ($value !== '' && ! in_array(Member::normalizeEnumValue($value), $allowed, true)) {
                    $report['invalidEnum'][] = ['line' => $line, 'field' => $field, 'value' => $value];
                }
            }

            $group = trim((string) ($row['group'] ?? ''));
            if ($group !== '') {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }
        }

        foreach ($emailLines as $email => $lines) {
            if (count($lines) > 1) {
                $report['dupEmailsInFile'][$email] = $lines;
            }
        }

        foreach ($groupCounts as $name => $count) {
            $group = Group::where('name', $name)->first();
            if (! $group) {
                $report['unmatchedGroups'][] = ['name' => $name, 'count' => $count];
                $report['groups'][] = ['name' => $name, 'count' => $count, 'matched' => false, 'gs' => null];

                continue;
            }
            $report['groups'][] = [
                'name' => $name,
                'count' => $count,
                'matched' => true,
                'gs' => $this->gatheringServiceFor($group),
            ];
        }

        usort($report['groups'], fn ($a, $b) => $b['count'] <=> $a['count']);

        return $report;
    }

    /**
     * @return array{0: array<string,bool>, 1: array<string,bool>, 2: array<string,bool>}
     */
    private function loadExistingMembers(): array
    {
        $existingEmails = [];
        $existingName = [];
        $existingNamePhone = [];

        // Each map value is the matched member's trashed state (true = soft-deleted,
        // so re-importing restores it). A LIVE member must win over a trashed
        // namesake, so we AND the flags together — false (live) is sticky.
        $note = function (array &$map, string $key, bool $trashed): void {
            $map[$key] = array_key_exists($key, $map) ? ($map[$key] && $trashed) : $trashed;
        };

        // withTrashed(): the unique email index counts soft-deleted rows, so the
        // importer matches (and restores) them rather than crashing on INSERT.
        Member::withTrashed()
            ->select(['first_name', 'last_name', 'email', 'phone_number', 'deleted_at'])
            ->cursor()
            ->each(function (Member $member) use (&$existingEmails, &$existingName, &$existingNamePhone, $note) {
                $trashed = $member->trashed();
                $email = mb_strtolower(trim((string) $member->email));
                if ($email !== '') {
                    $note($existingEmails, $email, $trashed);
                }
                $nameKey = mb_strtolower(trim((string) $member->first_name)) . '|' . mb_strtolower(trim((string) $member->last_name));
                $note($existingName, $nameKey, $trashed);
                $phone = preg_replace('/\D+/', '', (string) $member->phone_number);
                if ($phone !== '') {
                    $note($existingNamePhone, $nameKey . '|' . $phone, $trashed);
                }
            });

        return [$existingEmails, $existingName, $existingNamePhone];
    }

    private function gatheringServiceFor(Group $group): ?string
    {
        /** @var Collection<int, Group> $chain */
        $chain = collect([$group])->merge($group->ancestors());

        foreach ($chain as $node) {
            if (mb_strtolower((string) $node->groupType?->name) === 'gathering service') {
                return $node->name;
            }
        }

        return $chain->last()?->name;
    }
}
