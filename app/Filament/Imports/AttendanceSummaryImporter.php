<?php

namespace App\Filament\Imports;

use App\Models\AttendanceSummary;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class AttendanceSummaryImporter extends Importer
{
    protected static ?string $model = AttendanceSummary::class;

    public static function normalizeName(string $name): string
    {
        $name = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
            ["'", "'", '"', '"'],
            $name,
        );
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return mb_strtolower($name, 'UTF-8');
    }

    public static function getColumns(): array
    {
        return [];
    }

    public function resolveRecord(): ?AttendanceSummary
    {
        return new AttendanceSummary();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';
    }
}
