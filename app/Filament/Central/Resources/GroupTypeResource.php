<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\GroupTypeResource\Pages;
use App\Models\GroupType;
use Filament\Forms\Form;
use Filament\Tables\Table;

class GroupTypeResource extends TenantScopedResource
{
    protected static ?string $model = GroupType::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\GroupTypeResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\GroupTypeResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroupTypes::route('/'),
            'create' => Pages\CreateGroupType::route('/create'),
            'edit' => Pages\EditGroupType::route('/{record}/edit'),
        ];
    }
}
