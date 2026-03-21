<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\RoleDefinitionResource\Pages;
use App\Models\RoleDefinition;
use Filament\Forms\Form;
use Filament\Tables\Table;

class RoleDefinitionResource extends TenantScopedResource
{
    protected static ?string $model = RoleDefinition::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\RoleDefinitionResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\RoleDefinitionResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoleDefinitions::route('/'),
            'create' => Pages\CreateRoleDefinition::route('/create'),
            'edit' => Pages\EditRoleDefinition::route('/{record}/edit'),
        ];
    }
}
