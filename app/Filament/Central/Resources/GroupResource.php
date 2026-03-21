<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms\Form;
use Filament\Tables\Table;

class GroupResource extends TenantScopedResource
{
    protected static ?string $model = Group::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\GroupResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\GroupResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
