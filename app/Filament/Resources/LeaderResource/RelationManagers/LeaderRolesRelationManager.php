<?php

namespace App\Filament\Resources\LeaderResource\RelationManagers;

use App\Filament\Resources\GroupResource;
use App\Models\Group;
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
                    /* Confined to the signed-in admin's country, and required.
                       A role with no group leads nothing: it is not a lighter
                       version of a role, it is a role that does not work, and
                       it also used to make the leader invisible to the very
                       admin who created them. */
                    ->options(fn () => GroupResource::confineOptions(Group::query())
                        ->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
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
