<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\Pages\Concerns\SavesMemberGroups;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    use SavesMemberGroups;

    protected static string $resource = MemberResource::class;

    protected function afterCreate(): void
    {
        // The Bacenta picker does not save itself, and without a group the new
        // member is invisible to the country admin who just created them.
        $this->syncMemberGroups();
    }
}
