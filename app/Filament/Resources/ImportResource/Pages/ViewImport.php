<?php

namespace App\Filament\Resources\ImportResource\Pages;

use App\Filament\Resources\ImportResource;
use Filament\Actions\Imports\Models\Import;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Import Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('file_name')
                            ->label('File'),
                        Infolists\Components\TextEntry::make('total_rows')
                            ->label('Total Rows'),
                        Infolists\Components\TextEntry::make('successful_rows')
                            ->label('Successful'),
                        Infolists\Components\TextEntry::make('failed_rows_count')
                            ->label('Failed')
                            ->getStateUsing(fn (Import $record) => $record->failedRows()->count()),
                        Infolists\Components\TextEntry::make('status')
                            ->getStateUsing(fn (Import $record) => $record->completed_at ? 'Completed' : 'Processing')
                            ->badge()
                            ->color(fn (string $state) => $state === 'Completed' ? 'success' : 'warning'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Imported By'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Started')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime(),
                    ])
                    ->columns(4),
            ]);
    }
}
