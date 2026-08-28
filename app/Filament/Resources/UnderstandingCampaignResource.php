<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToAdminGroup;
use App\Filament\Resources\UnderstandingCampaignResource\Pages;
use App\Models\Group;
use App\Models\UnderstandingCampaign;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UnderstandingCampaignResource extends Resource
{
    use ScopesToAdminGroup;

    /**
     * ⚠ Not a single column, so the trait's simple version is overridden below.
     *
     * Confining on `allocated_group_id` alone was wrong in a way that hid work
     * rather than leaking it: a submission is unallocated until somebody places
     * it, so a country admin could not see the very entries they were supposed
     * to allocate. Worse, the "Not yet allocated" filter could never return
     * anything for them, because the base query had already dropped every row
     * with a null there.
     */
    protected static function scopeColumn(): ?string
    {
        return null;
    }

    /**
     * A submission belongs to this admin if the stream it came in through is
     * theirs, or if it has been allocated into their tree. Either is enough:
     * the first covers everything before allocation, the second covers a
     * submission moved into a group beneath them afterwards.
     */
    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        // null is the group-wide login and stays unrestricted. An empty
        // collection must still filter, or a misconfigured admin sees the lot.
        if ($ids === null) {
            return $query;
        }

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('stream_id', $ids)->orWhereIn('allocated_group_id', $ids);
        });
    }

    /**
     * The streams this admin may filter by.
     *
     * Its own method so the filter and its test share one implementation:
     * a scoping rule that the test reimplements is a scoping rule that can
     * pass while the screen is wrong.
     */
    public static function streamOptions(): Collection
    {
        $ids = User::currentScopeIds();

        $streams = Group::query()
            ->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['stream']));

        if ($ids !== null) {
            $streams->whereIn('id', $ids);
        }

        return $streams->orderBy('name')->pluck('name', 'id');
    }

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
                    Forms\Components\Select::make('stream_id')->label('Stream')->relationship('stream', 'name')->disabled(),
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
                Tables\Columns\TextColumn::make('stream.name')->label('Stream')->sortable(),
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
                /*
                 * ⚠ The options have to be confined too, not just the rows.
                 *
                 * `->relationship('stream', 'name')` lists every stream in the
                 * tenant. The rows were already scoped, so a Belgium admin was
                 * not seeing Swiss submissions, but the dropdown still named
                 * every Swiss and Dutch church back at them: Basel, Bern, Biel,
                 * Amsterdam. A filter that offers you forty churches and returns
                 * nothing for thirty-nine of them is also just broken.
                 */
                Tables\Filters\SelectFilter::make('stream_id')
                    ->label('Stream')
                    ->options(fn () => static::streamOptions())
                    ->searchable(),
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
