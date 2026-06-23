<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnderstandingCampaignResource\Pages;
use App\Models\UnderstandingCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnderstandingCampaignResource extends Resource
{
    protected static ?string $model = UnderstandingCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Understanding Campaign';

    protected static ?string $modelLabel = 'Understanding Campaign entry';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Submission')
                ->schema([
                    Forms\Components\DatePicker::make('attended_on')->label('Date')->disabled(),
                    Forms\Components\TextInput::make('first_name')->disabled(),
                    Forms\Components\TextInput::make('last_name')->label('Surname')->disabled(),
                    Forms\Components\TextInput::make('street_name')->disabled(),
                    Forms\Components\TextInput::make('postal_code')->disabled(),
                    Forms\Components\TextInput::make('phone_number')->disabled(),
                    Forms\Components\Toggle::make('re_dedicating')->label('Re-dedicating their life to Christ')->disabled(),
                    Forms\Components\Toggle::make('first_time')->label('First time at this church')->disabled(),
                    Forms\Components\TextInput::make('who_invited')->disabled(),
                ])->columns(2),

            Forms\Components\Section::make('Allocation')
                ->schema([
                    Forms\Components\Select::make('allocated_group_id')
                        ->label('Allocated Bacenta')
                        ->relationship(
                            'allocatedGroup',
                            'name',
                            fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)),
                        )
                        ->searchable()
                        ->preload(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attended_on')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('first_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('last_name')->label('Surname')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone_number')->searchable(),
                Tables\Columns\IconColumn::make('first_time')->label('First-timer')->boolean(),
                Tables\Columns\IconColumn::make('re_dedicating')->label('Re-dedicating')->boolean(),
                Tables\Columns\TextColumn::make('who_invited')->toggleable(),
                Tables\Columns\TextColumn::make('allocatedGroup.name')->label('Allocated Bacenta')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('attended_on', 'desc')
            ->filters([
                Tables\Filters\Filter::make('unallocated')
                    ->label('Not yet allocated')
                    ->query(fn ($query) => $query->whereNull('allocated_group_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnderstandingCampaigns::route('/'),
            'edit' => Pages\EditUnderstandingCampaign::route('/{record}/edit'),
        ];
    }
}
