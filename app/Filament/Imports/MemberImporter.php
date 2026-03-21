<?php

namespace App\Filament\Imports;

use App\Models\Member;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class MemberImporter extends Importer
{
    protected static ?string $model = Member::class;

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
                ->rules(['nullable', 'string', 'in:male,female,other'])
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
                ->rules(['nullable', 'string', 'in:not_started,in_progress,completed'])
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
                ->rules(['nullable', 'string', 'in:member,visitor,first_timer,new_convert'])
                ->example('member'),
            ImportColumn::make('member_since')
                ->rules(['nullable', 'date'])
                ->example('2024-01-01'),
            ImportColumn::make('notes')
                ->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): ?Member
    {
        // If email exists, update the existing member
        if ($this->data['email'] ?? null) {
            return Member::firstOrNew(['email' => $this->data['email']]);
        }

        // Otherwise create a new member
        return new Member();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your member import has completed. ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
