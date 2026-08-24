<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Group;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\NonMember;
use App\Models\RoleDefinition;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MemberResource extends Resource
{
    use ScopesToAdminGroup;

    /* Members reach a group through the group_member pivot, so one overlap
       is enough. A member in no group at all is deliberately NOT shown to a
       country admin: they belong to no country, and the unscoped group-wide
       admin still sees them, so nobody loses the ability to assign them. */
    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->whereHas(
            'groups',
            fn ($q) => $q->whereIn('groups.id', $ids),
        );
    }

    /**
     * A username in the house style: firstname.lastname, lowercased, spaces
     * and punctuation stripped, with a number appended if it is taken.
     *
     * Every one of the 72 existing leaders follows this, so a generated one
     * that does not would stand out and be typed wrongly.
     */
    public static function suggestUsername(Member $member): string
    {
        $part = fn (?string $s) => preg_replace('/[^a-z0-9]/', '', strtolower(
            iconv('UTF-8', 'ASCII//TRANSLIT', (string) $s) ?: (string) $s
        ));

        $base = trim($part($member->first_name).'.'.$part($member->last_name), '.');
        $base = $base !== '' && $base !== '.' ? $base : 'leader';

        $username = $base;
        $n = 1;
        while (Leader::where('username', $username)->exists()) {
            $username = $base.(++$n);
        }

        return $username;
    }

    /**
     * Turn a member into a leader.
     *
     * Extracted from the table action so it can be tested: the action itself
     * is a closure inside a static form definition and is awkward to reach.
     */
    public static function promoteToLeader(Member $member, array $data): Leader
    {
        return DB::transaction(function () use ($member, $data) {
            $leader = Leader::create([
                'member_id' => $member->id,
                'username' => $data['username'],
                'password' => $data['password'] ?: Setting::get('default_leader_password', 'Flock2026!'),
                'is_active' => true,
            ]);

            if (! empty($data['role_definition_id'])) {
                LeaderRole::create([
                    'leader_id' => $leader->id,
                    'role_definition_id' => $data['role_definition_id'],
                    'group_id' => $data['group_id'] ?? null,
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);
            }

            return $leader;
        });
    }

    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Name' => $record->full_name,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profile')
                    ->schema([
                        Forms\Components\FileUpload::make('picture')
                            ->label('Profile Picture')
                            ->image()
                            ->avatar()
                            ->directory('member-photos')
                            ->disk('public')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('first_name')
                            ->required(),
                        Forms\Components\TextInput::make('last_name')
                            ->required(),
                        Forms\Components\TextInput::make('phone_number'),
                        Forms\Components\DatePicker::make('date_of_birth'),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                            ]),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\Textarea::make('address')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('street_name')
                            ->label('Street name'),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal code'),
                        Forms\Components\TextInput::make('occupation')
                            ->label('Occupation / School'),
                    ])->columns(2),

                Forms\Components\Section::make('Church Info')
                    ->schema([
                        Forms\Components\Select::make('nbs_status')
                            ->label('NBS Status')
                            ->options(Member::NBS_STATUSES),
                        Forms\Components\Toggle::make('holy_ghost_baptism')
                            ->label('Holy Ghost Baptism'),
                        Forms\Components\Toggle::make('water_baptism')
                            ->label('Water Baptism'),
                        Forms\Components\Select::make('member_type')
                            ->label('Type of Member')
                            ->options(Member::MEMBER_TYPES),
                        Forms\Components\Select::make('bacenta_groups')
                            ->label('Bacenta')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->options(fn () => Group::query()
                                ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)
                                    ->whereRaw('LOWER(slug) != ?', ['basonta']))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Member $record) {
                                if (! $record) {
                                    return;
                                }
                                $component->state(
                                    $record->groups()
                                        ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) != ?', ['basonta']))
                                        ->pluck('groups.id')->all()
                                );
                            })
                            ->saveRelationshipsUsing(function (Member $record, $state) {
                                $managed = Group::query()
                                    ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) != ?', ['basonta']))
                                    ->pluck('id');
                                $record->groups()->detach($managed->all());
                                $selected = collect($state ?? [])
                                    ->map(fn ($v) => (int) $v)
                                    ->filter()
                                    ->intersect($managed)
                                    ->all();
                                if ($selected) {
                                    $record->groups()->attach($selected);
                                }
                            }),
                        Forms\Components\Select::make('basonta_groups')
                            ->label('Basonta')
                            ->helperText('Members assigned here appear in the Basonta leader\'s members list.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->options(fn () => Group::query()
                                ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['basonta']))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Member $record) {
                                if (! $record) {
                                    return;
                                }
                                $component->state(
                                    $record->groups()
                                        ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['basonta']))
                                        ->pluck('groups.id')->all()
                                );
                            })
                            ->saveRelationshipsUsing(function (Member $record, $state) {
                                $basonta = Group::query()
                                    ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['basonta']))
                                    ->pluck('id');
                                $record->groups()->detach($basonta->all());
                                $selected = collect($state ?? [])
                                    ->map(fn ($v) => (int) $v)
                                    ->filter()
                                    ->intersect($basonta)
                                    ->all();
                                if ($selected) {
                                    $record->groups()->attach($selected);
                                }
                            }),
                        Forms\Components\Toggle::make('profile_completed')
                            ->label('Profile Completed'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DatePicker::make('member_since'),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema(fn () => static::getAdditionalFieldsSchema())
                    ->visible(fn () => ! empty(static::getAdditionalFieldsConfig()))
                    ->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getAdditionalFieldsConfig(): array
    {
        try {
            return Setting::get('member_additional_fields', []) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function getAdditionalFieldsSchema(): array
    {
        $fields = static::getAdditionalFieldsConfig();
        $schema = [];

        foreach ($fields as $field) {
            $name = 'additional_info.'.$field['key'];
            $label = $field['label'];
            $type = $field['type'] ?? 'text';

            $schema[] = match ($type) {
                'toggle', 'boolean' => Forms\Components\Toggle::make($name)->label($label),
                'select' => Forms\Components\Select::make($name)
                    ->label($label)
                    ->options(collect($field['options'] ?? [])->mapWithKeys(fn ($o) => [$o => $o])->all()),
                'textarea' => Forms\Components\Textarea::make($name)->label($label),
                default => Forms\Components\TextInput::make($name)->label($label),
            };
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('picture')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->avatar_url),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone_number'),
                Tables\Columns\TextColumn::make('member_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\IconColumn::make('profile_completed')
                    ->label('Profile')
                    ->boolean(),
                Tables\Columns\IconColumn::make('leader')
                    ->label('Leader')
                    ->getStateUsing(fn (Member $record) => $record->leader !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-minus'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('member_type')
                    ->options(Member::MEMBER_TYPES),
                Tables\Filters\TernaryFilter::make('profile_completed'),
                Tables\Filters\TernaryFilter::make('is_leader')
                    ->label('Is Leader')
                    ->queries(
                        true: fn ($query) => $query->whereHas('leader'),
                        false: fn ($query) => $query->whereDoesntHave('leader'),
                    ),
                Tables\Filters\TernaryFilter::make('has_group')
                    ->label('In a Group')
                    ->placeholder('All')
                    ->trueLabel('In a group')
                    ->falseLabel('Unassigned')
                    ->queries(
                        true: fn ($query) => $query->whereHas('groups'),
                        false: fn ($query) => $query->whereDoesntHave('groups'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('makeLeader')
                    ->label('Make Leader')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Member $record) => ! $record->leader)
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->unique('leaders', 'username')
                            ->helperText('The name they type to log into the app.')
                            ->default(fn (Member $record) => static::suggestUsername($record)),
                        Forms\Components\TextInput::make('password')
                            ->required()
                            ->helperText('Shown once after saving. Change it to something unique for anyone who matters.')
                            ->default(fn () => Setting::get('default_leader_password', 'Flock2026!')),
                        Forms\Components\Select::make('role_definition_id')
                            ->label('Role')
                            ->options(RoleDefinition::active()->pluck('name', 'id'))
                            ->helperText('Without a role they can log in and see nothing.')
                            ->placeholder('No role'),
                        Forms\Components\Select::make('group_id')
                            ->label('Leading which group')
                            // Confined to the admin's own country: an unscoped
                            // list let a country admin put a leader in charge
                            // of another country's group.
                            ->options(fn () => GroupResource::confineOptions(Group::query())
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            /* Required as soon as a role is chosen. A role
                               pointing at no group leads nothing, and it also
                               dropped the leader out of every country's list. */
                            ->required(fn (Forms\Get $get) => filled($get('role_definition_id')))
                            ->visible(fn (Forms\Get $get) => filled($get('role_definition_id'))),
                    ])
                    ->action(function (Member $record, array $data) {
                        $leader = static::promoteToLeader($record, $data);

                        Notification::make()
                            ->title("{$record->full_name} is now a leader")
                            /* The password is shown here and nowhere else: it
                               is hashed on save and cannot be read back, so an
                               admin who does not copy it now has to reset it. */
                            ->body("Username: {$leader->username}  |  Password: {$data['password']}")
                            ->persistent()
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('makeUnregistered')
                    ->label('Make Unregistered')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->visible(fn (Member $record) => $record->member_type === 'member')
                    ->requiresConfirmation()
                    ->modalHeading('Make member unregistered')
                    ->modalDescription(fn (Member $record) => "This moves {$record->full_name} out of Members and into the Non-Members list. Their past attendance stays on their member record for historical reporting; from now on they're tracked as a non-member. This can be undone by restoring the member.")
                    ->modalSubmitActionLabel('Make unregistered')
                    ->action(function (Member $record) {
                        DB::transaction(function () use ($record) {
                            // Carry over the member's primary group (Non-Members hold a single group).
                            $primaryGroup = $record->groups()->wherePivot('is_primary', true)->first()
                                ?? $record->groups()->first();

                            NonMember::create([
                                'first_name' => $record->first_name,
                                'last_name' => $record->last_name,
                                'phone_number' => $record->phone_number,
                                'email' => $record->email,
                                'gender' => $record->gender,
                                'group_id' => $primaryGroup?->id,
                                'is_active' => $record->is_active,
                                'notes' => $record->notes,
                            ]);

                            // Soft-delete keeps the member row + attendance history for past reporting.
                            $record->delete();
                        });

                        Notification::make()
                            ->title("{$record->full_name} moved to Non-Members")
                            ->body('Their past attendance stays on their member record.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    /*
                     * Assigning to a bacenta is the step that makes a member
                     * countable: bacentas and basontas are the only tiers that
                     * track attendance. Without this, moving a country's roll
                     * onto its bacentas meant editing members one at a time,
                     * which for Switzerland's 78 is an afternoon.
                     */
                    Tables\Actions\BulkAction::make('assignBacenta')
                        ->label('Assign to Bacenta')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('bacenta_group_id')
                                ->label('Bacenta')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(fn () => GroupResource::confineOptions(
                                    Group::query()->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['bacenta']))
                                )->orderBy('name')->pluck('name', 'id')),
                            Forms\Components\Toggle::make('clear_holding_groups')
                                ->label('Take them off the stream they were parked on')
                                ->helperText('Members imported before their bacentas existed sit on a stream as a holding position. Leaving them there is untidy rather than harmful.')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $groupId = (int) $data['bacenta_group_id'];
                            $streamIds = Group::whereHas('groupType', fn ($q) => $q->where('slug', 'stream'))->pluck('id');

                            foreach ($records as $member) {
                                $member->groups()->syncWithoutDetaching([
                                    $groupId => ['is_primary' => true, 'joined_at' => now()->toDateString()],
                                ]);
                                if ($data['clear_holding_groups'] ?? false) {
                                    $member->groups()->detach($streamIds);
                                }
                            }

                            Notification::make()
                                ->title($records->count().' member(s) now on a bacenta')
                                ->body('They can be counted at a service from now on.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('assignBasonta')
                        ->label('Assign to Basonta')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('basonta_group_id')
                                ->label('Basonta')
                                ->required()
                                ->searchable()
                                ->preload()
                                /* Confined like every other group picker: this
                                   one still offered the whole tenant. */
                                ->options(fn () => GroupResource::confineOptions(
                                    Group::query()->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['basonta']))
                                )->orderBy('name')->pluck('name', 'id')),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $groupId = (int) $data['basonta_group_id'];
                            foreach ($records as $member) {
                                $member->groups()->syncWithoutDetaching([$groupId]);
                            }
                            Notification::make()
                                ->title($records->count().' member(s) assigned to Basonta')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
