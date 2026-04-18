<?php

namespace App\Filament\Imports;

use App\Models\AttendanceSummary;
use App\Models\Group;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

class AttendanceSummaryImporter extends Importer
{
    protected static ?string $model = AttendanceSummary::class;

    /** @var array<string, array<int, Group>> */
    protected static array $groupCache = [];

    /** @var array<string, true> */
    protected static array $unmatchedGroups = [];

    /** @var array<string, true> */
    protected static array $ambiguousGroups = [];

    public function __construct(
        Import $import,
        array $columnMap,
        array $options,
    ) {
        parent::__construct($import, $columnMap, $options);

        static::$groupCache = [];
        static::$unmatchedGroups = [];
        static::$ambiguousGroups = [];
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('date')
                ->requiredMapping()
                ->rules(['required', 'date'])
                ->example('2025-10-05'),
            ImportColumn::make('group')
                ->label('Bacenta Name')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->fillRecordUsing(fn () => null)
                ->example('Fruitfulness 1'),
            ImportColumn::make('total_attendance')
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:0'])
                ->example('40'),
        ];
    }

    public function resolveRecord(): ?AttendanceSummary
    {
        $groupName = trim((string) ($this->data['group'] ?? ''));
        $rawDate = $this->data['date'] ?? null;

        if ($groupName === '' || !$rawDate) {
            return null;
        }

        $date = \Carbon\Carbon::parse($rawDate)->toDateString();

        $group = static::resolveGroup($groupName);

        return AttendanceSummary::firstOrNew([
            'group_id' => $group->id,
            'date' => $date,
        ]);
    }

    public function beforeSave(): void
    {
        /** @var AttendanceSummary $record */
        $record = $this->record;

        if (!$record->exists) {
            return;
        }

        $record->attendances()->delete();
        $record->nonMemberAttendances()->delete();

        $record->visitor_count = 0;
        $record->first_timer_count = 0;
        $record->notes = null;
        $record->image = null;
        $record->submitted_by_leader_id = null;
    }

    /**
     * Looks up a Group by normalized name. Throws ValidationException on
     * missing or ambiguous matches so Filament records a specific reason on
     * the failed_import_rows row.
     */
    protected static function resolveGroup(string $rawName): Group
    {
        $key = static::normalizeName($rawName);

        if (!isset(static::$groupCache[$key])) {
            static::$groupCache[$key] = Group::query()
                ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
                ->get()
                ->filter(fn (Group $g) => static::normalizeName($g->name) === $key)
                ->values()
                ->all();
        }

        $matches = static::$groupCache[$key];

        if (count($matches) === 0) {
            static::$unmatchedGroups[$rawName] = true;
            throw ValidationException::withMessages([
                'group' => "Bacenta not found: {$rawName}",
            ]);
        }

        if (count($matches) > 1) {
            static::$ambiguousGroups[$rawName] = true;
            throw ValidationException::withMessages([
                'group' => "Ambiguous bacenta name: {$rawName}",
            ]);
        }

        return $matches[0];
    }

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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' failed.';
        }

        if (!empty(static::$unmatchedGroups)) {
            $names = implode(', ', array_keys(static::$unmatchedGroups));
            $body .= " Unmatched bacentas: {$names}.";
        }

        if (!empty(static::$ambiguousGroups)) {
            $names = implode(', ', array_keys(static::$ambiguousGroups));
            $body .= " Ambiguous bacenta names: {$names}.";
        }

        return $body;
    }
}
