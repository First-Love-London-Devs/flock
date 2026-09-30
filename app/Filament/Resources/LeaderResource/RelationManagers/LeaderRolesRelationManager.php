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
                    ->live()
                    ->required(),
                Forms\Components\Placeholder::make('tenant_wide_note')
                    ->hiddenLabel()
                    ->content('Super admins cover the whole church, so there is no group to pick.')
                    ->visible(fn (Forms\Get $get) => static::isTenantWideRole($get('role_definition_id'))),
                Forms\Components\Select::make('group_id')
                    ->label('Group')
                    /* Confined to the signed-in admin's country, and required for
                       every role EXCEPT the tenant-wide super admin, whose power
                       is expressed precisely by having no group (that is what
                       LeaderScopeService::isSuperAdmin reads). For every other
                       role a missing group leads nothing: not a lighter role, a
                       role that does not work, and it used to make the leader
                       invisible to the very admin who created them. */
                    ->options(fn () => GroupResource::confineOptions(Group::query())
                        ->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->hidden(fn (Forms\Get $get) => static::isTenantWideRole($get('role_definition_id')))
                    ->required(fn (Forms\Get $get) => ! static::isTenantWideRole($get('role_definition_id')))
                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => static::isTenantWideRole($get('role_definition_id')) ? null : $state),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    /**
     * A tenant-wide role (super admin) is one at permission level 100, matching
     * LeaderScopeService::isSuperAdmin. Such a role takes no group.
     */
    protected static function isTenantWideRole($roleDefinitionId): bool
    {
        return $roleDefinitionId
            && RoleDefinition::whereKey($roleDefinitionId)->where('permission_level', 100)->exists();
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
