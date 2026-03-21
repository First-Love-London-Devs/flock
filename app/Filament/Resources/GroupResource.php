<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Church Structure';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Select::make('group_type_id')
                    ->relationship('groupType', 'name')
                    ->required(),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->nullable()
                    ->searchable(),
                Forms\Components\Select::make('leader_id')
                    ->relationship('leader', 'username')
                    ->nullable()
                    ->searchable(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('meeting_day')
                    ->options([
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                    ]),
                Forms\Components\TimePicker::make('meeting_time'),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('groupType.name'),
                Tables\Columns\TextColumn::make('parent.name'),
                Tables\Columns\TextColumn::make('leader.member.full_name')
                    ->label('Leader'),
                Tables\Columns\TextColumn::make('total_members_count')
                    ->label('Members')
                    ->getStateUsing(fn ($record) => $record->total_members_count),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_type_id')
                    ->relationship('groupType', 'name'),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
