<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\AttendanceSummaryResource\Pages;
use App\Models\AttendanceSummary;
use Filament\Forms\Form;
use Filament\Tables\Table;

class AttendanceSummaryResource extends TenantScopedResource
{
    protected static ?string $model = AttendanceSummary::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\AttendanceSummaryResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\AttendanceSummaryResource::table($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\AttendanceSummaryResource\RelationManagers\AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceSummaries::route('/'),
            'view' => Pages\ViewAttendanceSummary::route('/{record}'),
        ];
    }
}
