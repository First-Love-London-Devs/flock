<?php

namespace App\Filament\Central\Resources\LeaderResource\Pages;

use App\Filament\Central\Resources\LeaderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeader extends EditRecord
{
    protected static string $resource = LeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
