<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends TenantScopedResource
{
    protected static ?string $model = Member::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Tenant Data';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return \App\Filament\Resources\MemberResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\MemberResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
