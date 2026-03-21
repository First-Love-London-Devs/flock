<?php

namespace App\Filament\Central\Pages;

use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TenantSwitcher extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Switch Tenant';
    protected static ?string $title = 'Switch Tenant';
    protected static ?int $navigationSort = -1;
    protected static string $view = 'filament.central.pages.tenant-switcher';

    public ?string $tenant_id = '';

    public function mount(): void
    {
        $this->tenant_id = session('selected_tenant_id', '');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tenant_id')
                    ->label('Select Tenant')
                    ->options(Tenant::pluck('church_name', 'id'))
                    ->searchable()
                    ->required()
                    ->helperText('Choose a tenant to manage their data.'),
            ]);
    }

    public function switchTenant(): void
    {
        $data = $this->form->getState();
        $tenant = Tenant::find($data['tenant_id']);

        if (!$tenant) {
            Notification::make()->title('Tenant not found')->danger()->send();
            return;
        }

        session(['selected_tenant_id' => $tenant->id]);

        Notification::make()
            ->title("Switched to {$tenant->church_name}")
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    public function clearTenant(): void
    {
        session()->forget('selected_tenant_id');
        $this->tenant_id = '';

        Notification::make()
            ->title('Tenant cleared')
            ->success()
            ->send();
    }

    public static function getActiveTenantName(): ?string
    {
        return \App\Http\Middleware\SetSelectedTenant::getSelectedTenant()?->church_name;
    }
}
