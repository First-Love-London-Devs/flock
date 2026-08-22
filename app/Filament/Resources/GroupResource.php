<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GroupResource extends Resource
{
    use ScopesToAdminGroup;

    /* A group is its own link to the tree, so match on the primary key. */
    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->whereIn('groups.id', $ids);
    }

    /**
     * The same confinement, for the dropdowns inside the form.
     *
     * Scoping the list but not the form is worse than not scoping at all: the
     * parent selector offered every group in the tenant, so a country admin
     * could hang a new bacenta under another country. The moment they saved
     * it, the group would sit outside their scope and vanish, leaving them
     * unable to see the thing they had just made, let alone move it back.
     */
    public static function confineOptions(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->whereIn('groups.id', $ids);
    }

    /** Leaders offered as a group's leader, confined the same way. */
    public static function confineLeaderOptions(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->where(
            fn ($q) => $q
                ->whereHas('leaderRoles', fn ($r) => $r->whereIn('group_id', $ids))
                ->orWhereDoesntHave('leaderRoles'),
        );
    }

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
                    ->relationship('parent', 'name', fn (Builder $query) => static::confineOptions($query))
                    ->nullable()
                    ->searchable(),
                Forms\Components\Select::make('leader_id')
                    ->relationship('leader', 'username', fn (Builder $query) => static::confineLeaderOptions($query))
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
            \App\Filament\Resources\GroupResource\RelationManagers\ChildrenRelationManager::class,
            \App\Filament\Resources\GroupResource\RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'view' => Pages\ViewGroup::route('/{record}'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
