<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleDefinitionResource\Pages;
use App\Models\RoleDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleDefinitionResource extends Resource
{
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
