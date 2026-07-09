<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceScheduleResource\Pages;
use App\Models\AttendanceSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceScheduleResource extends Resource
{
    protected static ?string $model = AttendanceSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Reminders';

    protected static ?string $modelLabel = 'Attendance reminder';

    /**
     * 0 = Sunday ... 6 = Saturday (Carbon's dayOfWeek convention).
     */
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Reminder window')
                ->description('During this window, holders of the chosen role are nudged to log the head count, but only while the counter is still empty for the day. One reminder per service.')
                ->schema([
                    Forms\Components\Select::make('stream_group_id')
                        ->label('Stream')
                        ->relationship('streamGroup', 'name', fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('slug', 'stream')))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('role_definition_id')
                        ->label('Notify role')
                        ->relationship('roleDefinition', 'name', fn ($query) => $query->where('is_active', true))
                        ->helperText('Everyone holding this role in the Stream gets the reminder.')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('day_of_week')
                        ->label('Service day')
                        ->options(self::DAYS)
                        ->required(),
                    Forms\Components\TimePicker::make('start_time')
                        ->label('From')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\TimePicker::make('end_time')
                        ->label('Until')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('streamGroup.name')->label('Stream')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roleDefinition.name')->label('Notify role')->sortable(),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn ($state) => self::DAYS[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')->label('From')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5)),
                Tables\Columns\TextColumn::make('end_time')->label('Until')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5)),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->filters([
                Tables\Filters\SelectFilter::make('day_of_week')->label('Day')->options(self::DAYS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceSchedules::route('/'),
            'create' => Pages\CreateAttendanceSchedule::route('/create'),
            'edit' => Pages\EditAttendanceSchedule::route('/{record}/edit'),
        ];
    }
}
