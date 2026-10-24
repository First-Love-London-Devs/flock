<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;
use App\Filament\Resources\CampRegistrationResource\Pages;
use App\Models\CampRegistration;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CampRegistrationResource extends Resource
{
    use ScopesToAdminGroup;

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

    /** A registration belongs to an admin when its church is in their tree. */
    protected static function scopeColumn(): ?string
    {
        return 'church_group_id';
    }

    /** Churches (streams) this admin may filter by — scoped, or the dropdown leaks other countries. */
    private static function churchOptions(): Collection
    {
        return static::scopedGroupOptions('stream');
    }

    private static function countryOptions(): Collection
    {
        return static::scopedGroupOptions('country');
    }

    private static function scopedGroupOptions(string $slug): Collection
    {
        $ids = User::currentScopeIds();

        $query = Group::query()
            ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', [$slug]));

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->orderBy('name')->pluck('name', 'id');
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
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('church.name')->label('Church')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('attending_dates')
                    ->label('Days')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' day'.(count($state) === 1 ? '' : 's') : '—'),
                Tables\Columns\IconColumn::make('needs_accommodation')->label('Accom.')->boolean(),
                Tables\Columns\IconColumn::make('bringing_children')->label('Children')->boolean(),
                Tables\Columns\TextColumn::make('number_of_children')->label('# Children')->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('travelling_from')->label('From')->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true)->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('country_group_id')
                    ->label('Country')
                    ->options(fn () => static::countryOptions())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('church_group_id')
                    ->label('Church')
                    ->options(fn () => static::churchOptions())
                    ->searchable(),
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
