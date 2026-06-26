<?php

namespace App\Filament\Pages;

use App\Services\MemberImportSanityChecker;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

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

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Import CSV')
                    ->helperText('Upload the SAME CSV you plan to import. Nothing is saved — this only checks it and reports what would happen.')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
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
            Notification::make()->title('Please choose a CSV file first.')->warning()->send();

            return;
        }

        $fullPath = Storage::disk('local')->path($path);
        [$headers, $rows] = $this->parseCsv($fullPath);

        // We only needed the file to read it — never keep the upload around.
        Storage::disk('local')->delete($path);
        $this->form->fill();

        if (empty($headers) || empty($rows)) {
            Notification::make()
                ->title('Could not read any rows from that file.')
                ->body('Make sure it is a CSV (not .xlsx) with a header row.')
                ->danger()
                ->send();

            return;
        }

        $this->report = app(MemberImportSanityChecker::class)->check($rows, $headers);

        Notification::make()->title('Sanity check complete.')->success()->send();
    }

    /**
     * @return array{0: array<int,string>, 1: array<int, array<string,string>>}
     */
    private function parseCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $headers = [];
        $rows = [];
        $isHeader = true;

        while (($cols = fgetcsv($handle)) !== false) {
            if ($isHeader) {
                $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cols[0] ?? '')); // strip UTF-8 BOM
                $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $cols);
                $isHeader = false;

                continue;
            }

            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // skip blank lines
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $cols[$idx] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$headers, $rows];
    }
}
