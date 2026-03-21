<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms\Form;
use Filament\Tables\Table;

class SettingResource extends TenantScopedResource
{
    protected static ?string $model = Setting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\SettingResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\SettingResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
