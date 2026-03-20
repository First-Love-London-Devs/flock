<?php

namespace App\Filament\Resources\AttendanceSummaryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Individual Attendance';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.first_name')
                    ->label('First Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.last_name')
                    ->label('Last Name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('attended')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_first_timer')
                    ->label('First Timer')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_visitor')
                    ->label('Visitor')
                    ->boolean(),
            ])
            ->defaultSort('attended', 'desc');
    }
}
