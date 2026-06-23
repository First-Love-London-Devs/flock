<?php

namespace App\Filament\Resources\UnderstandingCampaignResource\Pages;

use App\Filament\Resources\UnderstandingCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnderstandingCampaign extends EditRecord
{
    protected static string $resource = UnderstandingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
