<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

use App\Filament\Resources\LeaderResource\Pages;
use App\Models\Leader;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaderResource extends Resource
{
    use ScopesToAdminGroup;

    /* A leader belongs to the tree through the groups their active roles
       are attached to.

       Unplaced leaders, meaning no role or a role pointing at no group, are
       the awkward case. Showing them to everybody put thirteen Belgian
       leaders in Switzerland's list, which is a leak. Hiding them made six
       Swiss leaders invisible to the admin who had just created them.

       Neither is necessary, because a leader is still a person: even when the
       role says nothing, the member behind it usually sits in a group, and
       that group has a country. So an unplaced leader is claimed by whichever
       country their MEMBER is in, and only a leader whose member is in no
       group at all, whom no country can claim, is shown to everyone. */
    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        if ($ids === null) {
            return $query;
        }

        $unplaced = fn ($q) => $q->whereDoesntHave(
            'leaderRoles',
            fn ($r) => $r->whereNotNull('group_id'),
        );

        /* The tenant-wide account is not unplaced, it is everywhere.
           A super admin's role carries no group deliberately: that is how
           "the whole tenant" is written, and LeaderScopeService::isSuperAdmin()
           reads the permission level and ignores the group. Reading that null
           as "lost" listed the bishop under a country, where a country admin
           could open and edit him, which is a route to taking over the tenant.
           They belong to no country and are managed by the group-wide login. */
        $query->whereDoesntHave(
            'leaderRoles',
            fn ($r) => $r->where('is_active', true)
                ->whereNull('group_id')
                ->whereHas('roleDefinition', fn ($d) => $d->where('permission_level', 100)),
        );

        return $query->where(fn ($q) => $q
            // Placed inside my country by their role.
            ->whereHas(
                'leaderRoles',
                fn ($r) => $r->where('is_active', true)->whereIn('group_id', $ids),
            )
            // Unplaced, but the person behind them is in my country.
            ->orWhere(fn ($q2) => $unplaced($q2)
                ->whereHas('member.groups', fn ($g) => $g->whereIn('groups.id', $ids)))
            // Unplaced and the person is in no group either: nobody can claim
            // them, so everyone sees them rather than nobody.
            ->orWhere(fn ($q3) => $unplaced($q3)
                ->whereDoesntHave('member.groups')));
    }

    protected static ?string $model = Leader::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'People';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('member_id')
                    ->relationship('member', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Name')
                    ->searchable(['members.first_name', 'members.last_name']),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('leaderRoles.roleDefinition.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('leaderRoles.group.name')
                    ->label('Group')
                    ->separator(','),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->relationship('leaderRoles.roleDefinition', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
        return [
            \App\Filament\Resources\LeaderResource\RelationManagers\LeaderRolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaders::route('/'),
            'create' => Pages\CreateLeader::route('/create'),
            'view' => Pages\ViewLeader::route('/{record}'),
            'edit' => Pages\EditLeader::route('/{record}/edit'),
        ];
    }
}
