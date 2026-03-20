<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupTypeResource\Pages;
use App\Models\GroupType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GroupTypeResource extends Resource
{
    protected static ?string $model = GroupType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Church Structure';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('level')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\ColorPicker::make('color'),
                Forms\Components\TextInput::make('icon'),
                Forms\Components\Toggle::make('tracks_attendance'),
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
                Tables\Columns\TextColumn::make('level')
                    ->sortable(),
                Tables\Columns\IconColumn::make('tracks_attendance')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('groups_count')
                    ->counts('groups'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('tracks_attendance'),
            ])
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
            'index' => Pages\ListGroupTypes::route('/'),
            'create' => Pages\CreateGroupType::route('/create'),
            'edit' => Pages\EditGroupType::route('/{record}/edit'),
        ];
    }
}
