<?php

namespace App\Filament\Pages;

use App\Services\MemberImportSanityChecker;
use App\Support\MemberImportFileParser;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportSanityCheck extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Import Sanity Check';
    protected static ?int $navigationSort = 90;
    protected static ?string $title = 'Import Sanity Check';
    protected static string $view = 'filament.pages.import-sanity-check';

    public ?array $data = [];
    public ?array $report = null;
    public ?array $parsedRows = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Import file (CSV or Excel)')
                    ->helperText('Upload the CSV or .xlsx you plan to import. Nothing is saved — this only checks it and reports what would happen.')
                    ->acceptedFileTypes([
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->disk('local')
                    ->directory('import-sanity')
                    ->visibility('private')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function run(): void
    {
        $state = $this->form->getState();
        $stored = $state['file'] ?? null;
        $path = is_array($stored) ? (reset($stored) ?: null) : $stored;

        if (! $path) {
            Notification::make()->title('Please choose a file first.')->warning()->send();

            return;
        }

        $fullPath = Storage::disk('local')->path($path);
        [$headers, $rows] = app(MemberImportFileParser::class)->parse($fullPath);

        // We only needed the file to read it — never keep the upload around.
        Storage::disk('local')->delete($path);
        $this->form->fill();

        if (empty($headers) || empty($rows)) {
            $this->report = null;
            $this->parsedRows = null;
            Notification::make()
                ->title('Could not read any rows from that file.')
                ->body('Make sure it is a CSV or .xlsx with a header row.')
                ->danger()
                ->send();

            return;
        }

        $this->parsedRows = $rows;
        $this->report = app(MemberImportSanityChecker::class)->check($rows, $headers);

        Notification::make()->title('Sanity check complete.')->success()->send();
    }

    /**
     * Re-emit the parsed rows as an import-ready CSV (snake_case headers the
     * importer understands) so an uploaded .xlsx can be imported as-is.
     */
    public function downloadCsv(): ?StreamedResponse
    {
        if (empty($this->parsedRows)) {
            Notification::make()->title('Run a check first.')->warning()->send();

            return null;
        }

        $columns = MemberImportSanityChecker::EXPECTED_HEADERS;
        $rows = $this->parsedRows;

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($column) => $row[$column] ?? '', $columns));
            }
            fclose($out);
        }, 'import-ready.csv', ['Content-Type' => 'text/csv']);
    }
}
