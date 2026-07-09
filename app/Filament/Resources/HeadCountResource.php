<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeadCountResource\Pages;
use App\Models\HeadCount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HeadCountResource extends Resource
{
    protected static ?string $model = HeadCount::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Head Counts';

    protected static ?string $modelLabel = 'Head Count';

    protected static ?string $recordTitleAttribute = 'submitter_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Head count')
                ->schema([
                    Forms\Components\Select::make('group_id')->label('Bacenta')->relationship('group', 'name')->disabled(),
                    Forms\Components\DatePicker::make('date')->disabled(),
                    Forms\Components\TextInput::make('total_attendance')->label('Total present')->numeric()->disabled(),
                    Forms\Components\TextInput::make('first_timer_count')->label('First-timers')->numeric()->disabled(),
                    Forms\Components\TextInput::make('visitor_count')->label('Visitors')->numeric()->disabled(),
                    Forms\Components\TextInput::make('submitter_name')->label('Submitted by')->disabled(),
                    Forms\Components\Textarea::make('notes')->disabled()->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('group.name')->label('Bacenta')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('total_attendance')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('first_timer_count')->label('First-timers')->sortable(),
                Tables\Columns\TextColumn::make('visitor_count')->label('Visitors')->sortable(),
                Tables\Columns\TextColumn::make('submitter_name')->label('Submitted by')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Bacenta')
                    ->relationship('group', 'name')
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
            'index' => Pages\ListHeadCounts::route('/'),
        ];
    }
}
