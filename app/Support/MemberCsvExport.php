<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The roll, or a filtered slice of it, as a spreadsheet.
 *
 * Belgium asked to get the new converts out of the panel. The list already
 * exists there behind the "Type of Member" filter; what was missing was a way
 * to take it away and work it, which for follow-up means a phone number beside
 * a name and a column saying how far through New Believers School they are.
 *
 * It streams rather than building the file in memory, and it never queues:
 * this app runs QUEUE_CONNECTION=sync on a 2GB box shared with every other
 * site, so Filament's queued exporter would either block the request or need
 * a worker nobody is running.
 */
class MemberCsvExport
{
    /**
     * Columns chosen for following someone up, not for describing them.
     */
    private const COLUMNS = [
        'First name' => 'first_name',
        'Last name' => 'last_name',
        'Phone' => 'phone_number',
        'Email' => 'email',
        'Gender' => 'gender',
        'Date of birth' => 'date_of_birth',
        'Type' => 'member_type',
        'NBS status' => 'nbs_status',
        'Holy Ghost baptism' => 'holy_ghost_baptism',
        'Water baptism' => 'water_baptism',
        'Groups' => 'groups',
        'Member since' => 'member_since',
        'Added on' => 'created_at',
        'Active' => 'is_active',
    ];

    /**
     * @param  Builder|Collection  $source  an already-scoped
     *                                      query, or the rows the user ticked
     */
    public static function stream($source, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($source) {
            $out = fopen('php://output', 'w');

            // Excel reads a bare UTF-8 CSV as Windows-1252 and mangles every
            // accented name until it is told otherwise.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_keys(self::COLUMNS));

            $write = function ($members) use ($out) {
                foreach ($members as $member) {
                    fputcsv($out, self::row($member));
                }
            };

            if ($source instanceof Builder) {
                // chunkById, so a roll of any size never lands in memory at once.
                $source->with('groups')->chunkById(500, $write);
            } else {
                $source->loadMissing('groups');
                $write($source);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * A filename that says what the file holds and when it was taken, so two
     * downloads a week apart do not both sit in Downloads called members.csv.
     */
    public static function filename(?string $memberType = null): string
    {
        $what = $memberType
            ? strtolower(str_replace(' ', '-', Member::MEMBER_TYPES[$memberType] ?? $memberType)).'s'
            : 'members';

        return "{$what}-".Carbon::now()->format('Y-m-d').'.csv';
    }

    private static function row(Member $member): array
    {
        $yesNo = fn ($v) => $v ? 'Yes' : 'No';

        return [
            $member->first_name,
            $member->last_name,
            // Leading zeros survive: Excel eats 0470... as a number otherwise.
            $member->phone_number ? "\u{200B}".$member->phone_number : '',
            $member->email,
            $member->gender ? (Member::GENDERS[$member->gender] ?? $member->gender) : '',
            $member->date_of_birth?->format('Y-m-d'),
            Member::MEMBER_TYPES[$member->member_type] ?? $member->member_type,
            $member->nbs_status ? (Member::NBS_STATUSES[$member->nbs_status] ?? $member->nbs_status) : '',
            $yesNo($member->holy_ghost_baptism),
            $yesNo($member->water_baptism),
            $member->groups->pluck('name')->implode(', '),
            $member->member_since?->format('Y-m-d'),
            $member->created_at?->format('Y-m-d'),
            $yesNo($member->is_active),
        ];
    }
}
