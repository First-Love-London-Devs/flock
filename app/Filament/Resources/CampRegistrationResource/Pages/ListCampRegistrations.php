<?php

namespace App\Filament\Resources\CampRegistrationResource\Pages;

use App\Filament\Resources\CampRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListCampRegistrations extends ListRecords
{
    protected static string $resource = CampRegistrationResource::class;

    // Read-only list: no "New" action — registrations come in through the
    // public form, not the panel.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
