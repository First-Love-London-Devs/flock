<?php

namespace App\Filament\Resources;

use App\Models\User;

use App\Filament\Resources\RoleDefinitionResource\Pages;
use App\Models\RoleDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleDefinitionResource extends Resource
{
    /* Shared configuration. A country admin must not edit what every other
       country depends on, and hiding it is the honest version of that: it
       disappears from navigation rather than appearing and then refusing.
       The group-wide admin (no scope) still manages it. */
    public static function canViewAny(): bool
    {
        return User::currentScopeIds() === null;
    }

    protected static ?string $model = RoleDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('permission_level')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\Select::make('applies_to_group_type_id')
                    ->relationship('groupType', 'name')
                    ->nullable(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('permission_level')
                    ->sortable(),
                Tables\Columns\TextColumn::make('groupType.name')
                    ->label('Applies To'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
