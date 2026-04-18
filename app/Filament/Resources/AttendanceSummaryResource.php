<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceSummaryResource\Pages;
use App\Filament\Resources\AttendanceSummaryResource\RelationManagers;
use App\Models\AttendanceSummary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceSummaryResource extends Resource
{
    protected static ?string $model = AttendanceSummary::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Attendance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('group_id')
                    ->relationship('group', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('total_attendance')
                    ->numeric(),
                Forms\Components\TextInput::make('visitor_count')
                    ->numeric(),
                Forms\Components\TextInput::make('first_timer_count')
                    ->numeric(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('group.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_attendance')
                    ->sortable(),
                Tables\Columns\TextColumn::make('visitor_count'),
                Tables\Columns\TextColumn::make('first_timer_count'),
                Tables\Columns\TextColumn::make('submittedBy.member.full_name')
                    ->label('Submitted By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->relationship('group', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
        return [
            RelationManagers\AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceSummaries::route('/'),
            'view' => Pages\ViewAttendanceSummary::route('/{record}'),
        ];
    }
}
