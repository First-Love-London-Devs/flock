<?php

namespace App\Filament\Central\Resources\RoleDefinitionResource\Pages;

use App\Filament\Central\Resources\RoleDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoleDefinition extends EditRecord
{
    protected static string $resource = RoleDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
