<?php

namespace App\Filament\Resources\LeaderResource\RelationManagers;

use App\Models\RoleDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeaderRolesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaderRoles';
    protected static ?string $title = 'Roles';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role_definition_id')
                    ->label('Role')
                    ->options(RoleDefinition::active()->pluck('name', 'id'))
                    ->required(),
                Forms\Components\Select::make('group_id')
                    ->label('Group')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('roleDefinition.name')
                    ->label('Role')
                    ->badge(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Group')
                    ->default('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Role')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['assigned_at'] = now();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
