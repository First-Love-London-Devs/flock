<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampRegistrationResource\Pages;
use App\Http\Controllers\Web\CampRegistrationController;
use App\Models\CampRegistration;
use App\Models\Setting;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CampRegistrationResource extends Resource
{
    protected static ?string $model = CampRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Camp Registrations';

    protected static ?string $modelLabel = 'Camp registration';

    protected static ?string $recordTitleAttribute = 'full_name';

    /**
     * Only surface on a tenant that actually runs the camp. Off everywhere by
     * default, so the shared codebase never shows a "Camp Registrations" nav
     * item (backed by a table that may not exist) on gochurch, grace, etc.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Setting::get('camp_enabled', false);
    }

    public static function canAccess(): bool
    {
        return (bool) Setting::get('camp_enabled', false);
    }

    public static function form(Form $form): Form
    {
        // Read-only resource: no create/edit page, so the form is never shown.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('full_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone_number')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('country')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('denomination')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('needs_accommodation')->label('Accom.')->boolean(),
                Tables\Columns\IconColumn::make('bringing_children')->label('Children')->boolean(),
                Tables\Columns\TextColumn::make('number_of_children')->label('# Children')->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('travelling_from')->label('From')->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true)->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('country')
                    ->options(array_combine(CampRegistrationController::COUNTRIES, CampRegistrationController::COUNTRIES)),
                Tables\Filters\SelectFilter::make('denomination')
                    ->options(array_combine(CampRegistrationController::DENOMINATIONS, CampRegistrationController::DENOMINATIONS)),
                Tables\Filters\TernaryFilter::make('needs_accommodation')->label('Needs accommodation'),
                Tables\Filters\TernaryFilter::make('bringing_children')->label('Bringing children'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampRegistrations::route('/'),
        ];
    }
}
