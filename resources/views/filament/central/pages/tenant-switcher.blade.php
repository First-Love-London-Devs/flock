<x-filament-panels::page>
    @php
        $activeTenant = \App\Filament\Central\Pages\TenantSwitcher::getActiveTenantName();
    @endphp

    @if($activeTenant)
        <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Currently managing:</p>
                    <p class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ $activeTenant }}</p>
                </div>
                <x-filament::button color="gray" wire:click="clearTenant">
                    Clear Selection
                </x-filament::button>
            </div>
        </div>
    @endif

    <form wire:submit="switchTenant">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Switch Tenant
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
