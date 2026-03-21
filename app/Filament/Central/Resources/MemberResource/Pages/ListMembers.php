<?php

namespace App\Filament\Central\Resources\MemberResource\Pages;

use App\Filament\Central\Resources\MemberResource;
use App\Filament\Imports\MemberImporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(MemberImporter::class)
                ->label('Import Members'),
            Actions\CreateAction::make(),
        ];
    }
}
