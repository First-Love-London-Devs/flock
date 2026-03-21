<?php

namespace App\Filament\Resources\ImportResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FailedRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'failedRows';
    protected static ?string $title = 'Failed Rows';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('data')
                    ->label('Row Data')
                    ->getStateUsing(function ($record) {
                        $data = $record->data;
                        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                        $group = $data['group'] ?? '';

                        return $name . ($group ? " → {$group}" : '');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('validation_error')
                    ->label('Error')
                    ->wrap()
                    ->color('danger'),
            ]);
    }
}
