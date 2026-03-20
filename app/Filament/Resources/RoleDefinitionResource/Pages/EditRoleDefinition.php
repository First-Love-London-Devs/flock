<?php

namespace App\Filament\Resources\RoleDefinitionResource\Pages;

use App\Filament\Resources\RoleDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoleDefinition extends EditRecord
{
    protected static string $resource = RoleDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
