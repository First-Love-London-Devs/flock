<?php

namespace App\Filament\Resources\AttendanceScheduleResource\Pages;

use App\Filament\Resources\AttendanceScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSchedules extends ListRecords
{
    protected static string $resource = AttendanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
