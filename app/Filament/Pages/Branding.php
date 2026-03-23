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
    public ?string $color_primary = '';
    public ?string $color_secondary = '';
    public ?array $church_logo = [];
    public ?array $church_logo_dark = [];

    public function mount(): void
    {
        $logo = Setting::get('church_logo', '');
        $logoDark = Setting::get('church_logo_dark', '');

        $this->form->fill([
            'church_name' => Setting::get('church_name', ''),
            'church_tagline' => Setting::get('church_tagline', ''),
            'color_primary' => Setting::get('color_primary', '#4f46e5'),
            'color_secondary' => Setting::get('color_secondary', '#7c3aed'),
            'church_logo' => $logo ? [str_replace('/storage/', '', $logo)] : [],
            'church_logo_dark' => $logoDark ? [str_replace('/storage/', '', $logoDark)] : [],
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

                Forms\Components\Section::make('Colors')
                    ->schema([
                        Forms\Components\ColorPicker::make('color_primary')
                            ->label('Primary Color')
                            ->helperText('Main brand color used throughout the app.'),
                        Forms\Components\ColorPicker::make('color_secondary')
                            ->label('Secondary Color')
                            ->helperText('Accent color for highlights and secondary elements.'),
                    ])
                    ->columns(2),

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
        Setting::set('color_primary', $data['color_primary'] ?? '#4f46e5');
        Setting::set('color_secondary', $data['color_secondary'] ?? '#7c3aed');

        $logo = collect($data['church_logo'] ?? [])->first();
        $logoDark = collect($data['church_logo_dark'] ?? [])->first();

        Setting::set('church_logo', $logo ? '/storage/' . $logo : '');
        Setting::set('church_logo_dark', $logoDark ? '/storage/' . $logoDark : '');

        Notification::make()
            ->title('Branding updated')
            ->success()
            ->send();
    }
}
