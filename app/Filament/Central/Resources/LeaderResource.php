<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\LeaderResource\Pages;
use App\Models\Leader;
use Filament\Forms\Form;
use Filament\Tables\Table;

class LeaderResource extends TenantScopedResource
{
    protected static ?string $model = Leader::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\LeaderResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\LeaderResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaders::route('/'),
            'create' => Pages\CreateLeader::route('/create'),
            'edit' => Pages\EditLeader::route('/{record}/edit'),
        ];
    }
}
