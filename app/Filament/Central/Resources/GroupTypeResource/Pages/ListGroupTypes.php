<?php

namespace App\Filament\Central\Resources\GroupTypeResource\Pages;

use App\Filament\Central\Resources\GroupTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupTypes extends ListRecords
{
    protected static string $resource = GroupTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
