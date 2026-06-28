<?php

namespace App\Filament\Imports;

use App\Models\Group;
use App\Models\Member;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class MemberImporter extends Importer
{
    protected static ?string $model = Member::class;

    protected static array $groupCache = [];
    protected static array $unmatchedGroups = [];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('John'),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Smith'),
            ImportColumn::make('email')
                ->rules(['nullable', 'email'])
                ->example('john@example.com'),
            ImportColumn::make('phone_number')
                ->rules(['nullable', 'string', 'max:50'])
                ->example('+44 7700 900000'),
            ImportColumn::make('date_of_birth')
                ->rules(['nullable', 'date'])
                ->example('1990-01-15'),
            ImportColumn::make('gender')
                ->castStateUsing(fn ($state) => Member::normalizeEnumValue($state))
                ->rules(['nullable', 'string', 'in:' . implode(',', array_keys(Member::GENDERS))])
                ->example('male'),
            ImportColumn::make('address')
                ->rules(['nullable', 'string'])
                ->example('123 Main St, London'),
            ImportColumn::make('occupation')
                ->label('Occupation / School')
                ->rules(['nullable', 'string', 'max:255'])
                ->example('Software Engineer'),
            ImportColumn::make('marital_status')
                ->rules(['nullable', 'string'])
                ->example('single'),
            ImportColumn::make('nbs_status')
                ->label('NBS Status')
                ->castStateUsing(fn ($state) => Member::normalizeEnumValue($state))
                ->rules(['nullable', 'string', 'in:' . implode(',', array_keys(Member::NBS_STATUSES))])
                ->example('completed'),
            ImportColumn::make('holy_ghost_baptism')
                ->label('Holy Ghost Baptism')
                ->castStateUsing(fn ($state) => filter_var($state, FILTER_VALIDATE_BOOLEAN))
                ->rules(['nullable', 'boolean'])
                ->example('yes'),
            ImportColumn::make('water_baptism')
                ->label('Water Baptism')
                ->castStateUsing(fn ($state) => filter_var($state, FILTER_VALIDATE_BOOLEAN))
                ->rules(['nullable', 'boolean'])
                ->example('yes'),
            ImportColumn::make('member_type')
                ->label('Type of Member')
                ->castStateUsing(fn ($state) => Member::normalizeEnumValue($state))
                ->rules(['nullable', 'string', 'in:' . implode(',', array_keys(Member::MEMBER_TYPES))])
                ->example('member'),
            ImportColumn::make('member_since')
                ->rules(['nullable', 'date'])
                ->example('2024-01-01'),
            ImportColumn::make('notes')
                ->rules(['nullable', 'string']),
            ImportColumn::make('group')
                ->label('Group (Bacenta)')
                ->fillRecordUsing(fn () => null)
                ->example('Predestination 1'),
        ];
    }

    public function resolveRecord(): ?Member
    {
        $email = trim((string) ($this->data['email'] ?? ''));

        // Match on email case-insensitively AND including soft-deleted members:
        // the unique index is case-insensitive and still counts trashed rows, so
        // either difference would otherwise slip past and crash with a duplicate
        // -key error. A trashed match is restored — re-importing a removed member
        // means they are active again.
        if ($email !== '') {
            $member = Member::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

            return $member ? $this->restoreIfTrashed($member) : new Member();
        }

        // No email: dedupe on name (plus phone when present) so re-running the
        // same import reconciles existing rows instead of creating duplicates.
        $first = trim((string) ($this->data['first_name'] ?? ''));
        $last = trim((string) ($this->data['last_name'] ?? ''));
        $phone = trim((string) ($this->data['phone_number'] ?? ''));

        if ($first === '' && $last === '') {
            return new Member();
        }

        $match = function (bool $withTrashed) use ($first, $last, $phone) {
            $query = ($withTrashed ? Member::withTrashed() : Member::query())
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($first)])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($last)]);

            if ($phone !== '') {
                $query->where('phone_number', $phone);
            }

            return $query->first();
        };

        // Prefer a live namesake; only fall back to (and restore) a trashed one.
        $member = $match(false) ?? $match(true);

        return $member ? $this->restoreIfTrashed($member) : new Member();
    }

    private function restoreIfTrashed(Member $member): Member
    {
        if ($member->trashed()) {
            $member->restore();
        }

        return $member;
    }

    public function afterSave(): void
    {
        $groupName = trim($this->originalData['group'] ?? '');

        if (!$groupName) {
            return;
        }

        if (!isset(static::$groupCache[$groupName])) {
            static::$groupCache[$groupName] = Group::where('name', $groupName)->first();
        }

        $group = static::$groupCache[$groupName];

        if (!$group) {
            static::$unmatchedGroups[$groupName] = true;

            $this->record->update([
                'notes' => trim(($this->record->notes ?? '') . "\n[Import] Group not found: {$groupName}"),
            ]);

            return;
        }

        $this->record->groups()->syncWithoutDetaching([
            $group->id => [
                'joined_at' => $this->record->member_since ?? now()->toDateString(),
                'is_primary' => true,
            ],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' failed.';
        }

        if (!empty(static::$unmatchedGroups)) {
            $names = implode(', ', array_keys(static::$unmatchedGroups));
            $body .= " Unmatched groups: {$names}";
        }

        return $body;
    }
}
