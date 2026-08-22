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

       Leaders with no role at all are shown to every scoped admin as well.
       Creating one is two steps in the panel, the login and then the role, and
       between them the leader has no role: scoping purely on roles made it
       vanish the instant it was saved, leaving the admin who had just created
       it unable to reach step two. Same trade as the group-less members on the
       API side, and for the same reason: being seen by a country that turns
       out not to own them is recoverable, being seen by nobody is not. */
    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->where(
            fn ($q) => $q
                ->whereHas(
                    'leaderRoles',
                    fn ($r) => $r->where('is_active', true)->whereIn('group_id', $ids),
                )
                ->orWhereDoesntHave('leaderRoles'),
        );
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
