<?php

namespace App\Filament\Resources\GroupTypeResource\Pages;

use App\Filament\Resources\GroupTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupType extends EditRecord
{
    protected static string $resource = GroupTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
