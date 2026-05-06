<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NonMemberResource\Pages;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\NonMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class NonMemberResource extends Resource
{
    protected static ?string $model = NonMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

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
                        Forms\Components\TextInput::make('first_name')
                            ->required(),
                        Forms\Components\TextInput::make('last_name')
                            ->required(),
                        Forms\Components\TextInput::make('phone_number'),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                            ]),
                        Forms\Components\Select::make('group_id')
                            ->label('Bacenta')
                            ->relationship(
                                'group',
                                'name',
                                fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)),
                            )
                            ->searchable()
                            ->preload(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone_number'),
                Tables\Columns\TextColumn::make('email')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Bacenta')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Bacenta')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mergeIntoMember')
                    ->label('Merge into Member')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->modalHeading('Merge non-member into existing member')
                    ->modalDescription('Attendance rows will be moved to the chosen member. If that member already has an attendance row for the same date, the existing row is kept and the non-member one is dropped. The non-member record is then deleted.')
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Merge into')
                            ->options(fn () => Member::query()
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn (Member $m) => [
                                    $m->id => trim("{$m->first_name} {$m->last_name}") . ($m->email ? " ({$m->email})" : ''),
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (NonMember $record, array $data) {
                        $member = Member::findOrFail($data['member_id']);

                        $migrated = 0;
                        $skipped = 0;

                        DB::transaction(function () use ($record, $member, &$migrated, &$skipped) {
                            foreach ($record->attendances as $nma) {
                                $exists = Attendance::where('member_id', $member->id)
                                    ->where('attendance_summary_id', $nma->attendance_summary_id)
                                    ->exists();

                                if ($exists) {
                                    $skipped++;
                                    continue;
                                }

                                Attendance::create([
                                    'attendance_summary_id' => $nma->attendance_summary_id,
                                    'member_id' => $member->id,
                                    'attended' => $nma->attended,
                                    'is_first_timer' => $nma->is_first_timer,
                                    'is_new_convert' => $nma->is_new_convert,
                                    'is_visitor' => false,
                                ]);
                                $migrated++;
                            }

                            $record->delete();
                        });

                        Notification::make()
                            ->title("Merged into {$member->full_name}")
                            ->body("{$migrated} attendance row(s) migrated, {$skipped} skipped (already present on member).")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListNonMembers::route('/'),
            'create' => Pages\CreateNonMember::route('/create'),
            'edit' => Pages\EditNonMember::route('/{record}/edit'),
        ];
    }
}
