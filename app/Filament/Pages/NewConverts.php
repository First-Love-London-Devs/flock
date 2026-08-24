<?php

namespace App\Filament\Pages;

use App\Support\NewConvertsCsvExport;
use App\Support\NewConvertsReport;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
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
            ])
            ->columns(2)
            ->statePath('data');
    }

    /** @return Collection<int, array> */
    public function getRowsProperty(): Collection
    {
        return NewConvertsReport::rows(
            $this->data['from'] ?? null,
            $this->data['to'] ?? null,
        );
    }

    public function export(): StreamedResponse
    {
        $from = $this->data['from'] ?? null;
        $to = $this->data['to'] ?? null;

        return NewConvertsCsvExport::stream(
            NewConvertsReport::rows($from, $to),
            NewConvertsCsvExport::filename($from, $to),
        );
    }
}
