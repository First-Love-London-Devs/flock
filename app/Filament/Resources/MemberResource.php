<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
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
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\Textarea::make('address')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('occupation')
                            ->label('Occupation / School'),
                    ])->columns(2),

                Forms\Components\Section::make('Church Info')
                    ->schema([
                        Forms\Components\Select::make('nbs_status')
                            ->label('NBS Status')
                            ->options([
                                'not_started' => 'Not Started',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ]),
                        Forms\Components\Toggle::make('holy_ghost_baptism')
                            ->label('Holy Ghost Baptism'),
                        Forms\Components\Toggle::make('water_baptism')
                            ->label('Water Baptism'),
                        Forms\Components\Select::make('member_type')
                            ->label('Type of Member')
                            ->options([
                                'member' => 'Member',
                                'visitor' => 'Visitor',
                                'first_timer' => 'First Timer',
                                'new_convert' => 'New Convert',
                            ]),
                        Forms\Components\Select::make('groups')
                            ->label('Bacenta')
                            ->relationship(
                                'groups',
                                'name',
                                fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Toggle::make('profile_completed')
                            ->label('Profile Completed'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DatePicker::make('member_since'),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema(fn () => static::getAdditionalFieldsSchema())
                    ->visible(fn () => !empty(static::getAdditionalFieldsConfig()))
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
            $name = 'additional_info.' . $field['key'];
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
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . '&background=random'),
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
                    ->options([
                        'member' => 'Member',
                        'visitor' => 'Visitor',
                        'first_timer' => 'First Timer',
                        'new_convert' => 'New Convert',
                    ]),
                Tables\Filters\TernaryFilter::make('profile_completed'),
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
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
