<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Imports\MemberImporter;
use App\Filament\Resources\MemberResource;
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
