<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceCounterResource\Pages;
use App\Models\AttendanceCounter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceCounterResource extends Resource
{
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
