<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Branding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Branding';
    protected static ?string $title = 'Branding';
    protected static string $view = 'filament.pages.branding';

    public ?string $church_name = '';
    public ?string $church_tagline = '';
    public ?string $church_logo = '';
    public ?string $church_logo_dark = '';

    public function mount(): void
    {
        $this->form->fill([
            'church_name' => Setting::get('church_name', ''),
            'church_tagline' => Setting::get('church_tagline', ''),
            'church_logo' => Setting::get('church_logo', ''),
            'church_logo_dark' => Setting::get('church_logo_dark', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Church Identity')
                    ->schema([
                        Forms\Components\TextInput::make('church_name')
                            ->label('Church Name')
                            ->required()
                            ->helperText('Displayed in the admin panel header and mobile app.'),
                        Forms\Components\TextInput::make('church_tagline')
                            ->label('Tagline')
                            ->helperText('Optional tagline shown in the mobile app.'),
                    ]),

                Forms\Components\Section::make('Logo')
                    ->schema([
                        Forms\Components\FileUpload::make('church_logo')
                            ->label('Logo (Light Mode)')
                            ->image()
                            ->directory('branding')
                            ->disk('public')
                            ->helperText('Used on light backgrounds. Recommended: PNG with transparent background.'),
                        Forms\Components\FileUpload::make('church_logo_dark')
                            ->label('Logo (Dark Mode)')
                            ->image()
                            ->directory('branding')
                            ->disk('public')
                            ->helperText('Used on dark backgrounds. Leave empty to use the light mode logo.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('church_name', $data['church_name']);
        Setting::set('church_tagline', $data['church_tagline'] ?? '');

        $logo = $data['church_logo'] ?? '';
        $logoDark = $data['church_logo_dark'] ?? '';

        Setting::set('church_logo', $logo ? '/storage/' . $logo : '');
        Setting::set('church_logo_dark', $logoDark ? '/storage/' . $logoDark : '');

        Notification::make()
            ->title('Branding updated')
            ->success()
            ->send();
    }
}
