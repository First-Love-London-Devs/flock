<?php

namespace App\Filament\Resources\NonMemberResource\Pages;

use App\Filament\Resources\NonMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNonMember extends EditRecord
{
    protected static string $resource = NonMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
