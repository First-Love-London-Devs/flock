<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportResource\Pages;
use App\Filament\Resources\ImportResource\RelationManagers;
use Filament\Actions\Imports\Models\Import;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Import History';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Total'),
                Tables\Columns\TextColumn::make('successful_rows')
                    ->label('Successful')
                    ->color('success'),
                Tables\Columns\TextColumn::make('failed_rows_count')
                    ->label('Failed')
                    ->counts('failedRows')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (Import $record) => $record->completed_at ? 'Completed' : 'Processing')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Completed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Imported By'),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FailedRowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
            'view' => Pages\ViewImport::route('/{record}'),
        ];
    }
}
