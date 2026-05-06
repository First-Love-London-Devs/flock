<?php

namespace App\Filament\Resources\NonMemberResource\Pages;

use App\Filament\Resources\NonMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNonMembers extends ListRecords
{
    protected static string $resource = NonMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
