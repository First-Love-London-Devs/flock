<?php

namespace App\Filament\Pages;

use App\Models\Group;
use App\Models\User;
use App\Support\NewConvertsCsvExport;
use App\Support\NewConvertsReport;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who the new converts are, not just how many.
 *
 * The dashboards have always shown a new-convert count per service. Belgium
 * asked to export the list behind it, which turned out not to exist anywhere
 * in the panel: the count is derived from flags on attendance records, and
 * nothing ever showed the people carrying them.
 *
 * Half of those people are not on the members roll at all, so this could not
 * be a filter on the members table. See NewConvertsReport.
 */
class NewConverts extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'New Converts';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'New Converts';

    protected static string $view = 'filament.pages.new-converts';

    public ?array $data = [];

    public function mount(): void
    {
        // Default to this year: long enough to be the whole story for most
        // churches, short enough not to read every attendance row ever.
        $this->form->fill([
            'from' => now()->startOfYear()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('from')
                    ->label('Marked from')
                    ->native(false),
                Forms\Components\DatePicker::make('to')
                    ->label('Marked up to')
                    ->native(false),

                /* A service is a stream — "Gospel Experience Service",
                   "Jesus Encounter Service" — and choosing one means everything
                   counted beneath it. */
                Forms\Components\Select::make('service')
                    ->label('Service')
                    ->placeholder('Every service')
                    ->options(fn () => self::scoped(
                        Group::query()->whereHas('groupType', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['stream']))
                    )->orderBy('name')->pluck('name', 'id'))
                    ->live()
                    // Clear a group that the newly chosen service does not contain,
                    // rather than leaving a stale pair that returns nothing.
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('group', null))
                    ->native(false),

                Forms\Components\Select::make('group')
                    ->label('Group')
                    ->placeholder('Every group')
                    ->options(function (Forms\Get $get) {
                        $query = self::scoped(
                            Group::query()->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
                        );

                        // Narrowed by the service above, so the list is only ever
                        // groups that could actually appear in the result.
                        if ($service = $get('service')) {
                            $ids = Group::find($service)?->allGroupIds() ?? collect();
                            $query->whereIn('id', $ids);
                        }

                        return $query->orderBy('name')->pluck('name', 'id');
                    })
                    ->searchable()
                    ->live()
                    ->native(false),
            ])
            ->columns(2)
            ->statePath('data');
    }

    /** Only ever offer what this admin is allowed to see. */
    private static function scoped(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    /** @return Collection<int, array> */
    public function getRowsProperty(): Collection
    {
        return NewConvertsReport::rows(
            $this->data['from'] ?? null,
            $this->data['to'] ?? null,
            ($this->data['service'] ?? null) ? (int) $this->data['service'] : null,
            ($this->data['group'] ?? null) ? (int) $this->data['group'] : null,
        );
    }

    public function export(): StreamedResponse
    {
        $from = $this->data['from'] ?? null;
        $to = $this->data['to'] ?? null;
        $service = ($this->data['service'] ?? null) ? (int) $this->data['service'] : null;
        $group = ($this->data['group'] ?? null) ? (int) $this->data['group'] : null;

        // The narrower of the two names the file, so a folder of exports can be
        // told apart without opening them.
        $named = Group::find($group ?? $service)?->name;

        return NewConvertsCsvExport::stream(
            NewConvertsReport::rows($from, $to, $service, $group),
            NewConvertsCsvExport::filename($from, $to, $named),
        );
    }
}
