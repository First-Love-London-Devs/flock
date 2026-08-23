<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;

use App\Filament\Resources\AttendanceCounterResource\Pages;
use App\Models\AttendanceCounter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceCounterResource extends Resource
{
    use ScopesToAdminGroup;

    protected static function scopeColumn(): ?string
    {
        return 'group_id';
    }

    protected static ?string $model = AttendanceCounter::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Counter';

    protected static ?string $modelLabel = 'Attendance count';

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Count')
                ->schema([
                    Forms\Components\Select::make('group_id')->label('Stream')->relationship('group', 'name')->disabled(),
                    Forms\Components\DatePicker::make('date')->disabled(),
                    Forms\Components\TextInput::make('first_time_count')->label('First time')->numeric()->disabled(),
                    Forms\Components\TextInput::make('returning_count')->label('Been here before')->numeric()->disabled(),
                    Forms\Components\TextInput::make('regular_count')->label('Regular')->numeric()->disabled(),
                    Forms\Components\TextInput::make('visitor_count')->label('Visitor')->numeric()->disabled(),
                    Forms\Components\DateTimePicker::make('reset_at')->label('Last reset')->disabled(),
                ])->columns(2),
        ]);
    }

    /**
     * Apply a correction.
     *
     * Extracted so it can be tested: the action body is a closure inside a
     * static table definition and is awkward to reach from a test.
     */
    public static function correct(AttendanceCounter $record, array $data): AttendanceCounter
    {
        $record->update([
            'first_time_count' => (int) $data['first_time_count'],
            'returning_count' => (int) $data['returning_count'],
            'regular_count' => (int) $data['regular_count'],
            'visitor_count' => (int) $data['visitor_count'],
            'corrected_at' => now(),
            'corrected_by' => auth()->user()?->name ?? auth()->user()?->email,
            'correction_note' => $data['correction_note'],
        ]);

        return $record;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('group.name')->label('Stream')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('first_time_count')->label('First time')->sortable(),
                Tables\Columns\TextColumn::make('returning_count')->label('Returning')->sortable(),
                Tables\Columns\TextColumn::make('regular_count')->label('Regular')->sortable(),
                Tables\Columns\TextColumn::make('visitor_count')->label('Visitor')->sortable(),
                Tables\Columns\TextColumn::make('total_count')->label('Total')->weight('bold'),
                Tables\Columns\TextColumn::make('updated_at')->label('Last tap')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                /* A corrected total is a different kind of fact from a counted
                   one, so it says so rather than looking identical. */
                Tables\Columns\TextColumn::make('corrected_at')
                    ->label('Corrected')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state, AttendanceCounter $record) => $state
                        ? 'by '.($record->corrected_by ?: 'someone').' '.$state->diffForHumans()
                        : null)
                    ->tooltip(fn (AttendanceCounter $record) => $record->correction_note)
                    ->placeholder(''),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Stream')
                    ->relationship('group', 'name', fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('slug', 'stream')))
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                /*
                 * Ushers miscount. Every field on this record is disabled, so
                 * until now the only remedy was someone editing the database,
                 * which left a figure indistinguishable from a real count.
                 *
                 * This sets the numbers and records that it was set, by whom
                 * and why. The tap log is deliberately left alone: it is what
                 * actually happened, and rewriting history to match a
                 * correction would destroy the only evidence of the mistake.
                 */
                Tables\Actions\Action::make('correctCounts')
                    ->label('Correct counts')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Correct a miscount')
                    ->modalDescription('Use this when the counting itself went wrong. The tap history is kept as it was, and the record will show that these numbers were corrected.')
                    ->modalSubmitActionLabel('Save correction')
                    ->fillForm(fn (AttendanceCounter $record) => [
                        'first_time_count' => $record->first_time_count,
                        'returning_count' => $record->returning_count,
                        'regular_count' => $record->regular_count,
                        'visitor_count' => $record->visitor_count,
                    ])
                    ->form([
                        Forms\Components\TextInput::make('first_time_count')->label('First time')->numeric()->minValue(0)->required(),
                        Forms\Components\TextInput::make('returning_count')->label('Been here before')->numeric()->minValue(0)->required(),
                        Forms\Components\TextInput::make('regular_count')->label('Regular')->numeric()->minValue(0)->required(),
                        Forms\Components\TextInput::make('visitor_count')->label('Visitor')->numeric()->minValue(0)->required(),
                        Forms\Components\Textarea::make('correction_note')
                            ->label('What went wrong')
                            ->placeholder('e.g. the ushers double-counted the side door')
                            ->required()
                            ->helperText('Required. In a month nobody will remember why this number changed.'),
                    ])
                    ->action(fn (AttendanceCounter $record, array $data) => static::correct($record, $data))
                    ->successNotificationTitle('Counts corrected'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceCounters::route('/'),
        ];
    }
}
