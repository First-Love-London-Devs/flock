<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The new converts list as a spreadsheet, ready to be worked through.
 */
class NewConvertsCsvExport
{
    private const HEADINGS = [
        'Name', 'Phone', 'Email', 'On roll', 'Group',
        'First marked', 'Last marked', 'Times marked', 'NBS status',
    ];

    public static function stream(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // Without this Excel reads UTF-8 as Windows-1252 and mangles names.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADINGS);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    // Zero-width space, or Excel drops the leading 0 off 0470...
                    $row['phone'] ? "\u{200B}".$row['phone'] : '',
                    $row['email'],
                    $row['on_roll'],
                    $row['group'],
                    $row['first_seen'],
                    $row['last_seen'],
                    $row['times_marked'],
                    $row['nbs_status'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function filename(?string $from = null, ?string $to = null): string
    {
        $window = $from || $to
            ? ($from ?: 'start').'-to-'.($to ?: Carbon::now()->format('Y-m-d'))
            : Carbon::now()->format('Y-m-d');

        return "new-converts-{$window}.csv";
    }
}
