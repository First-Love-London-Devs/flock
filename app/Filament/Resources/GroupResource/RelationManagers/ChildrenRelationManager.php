<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';
    protected static ?string $title = 'Sub Groups';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('groupType.name')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('leader.member.full_name')
                    ->label('Leader'),
                Tables\Columns\TextColumn::make('total_members_count')
                    ->label('Members')
                    ->getStateUsing(fn ($record) => $record->total_members_count),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\GroupResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
