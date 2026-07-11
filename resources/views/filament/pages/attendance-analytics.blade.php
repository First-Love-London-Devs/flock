<x-filament-panels::page>
    {{ $this->filtersForm }}

    <x-filament-widgets::widgets
        :columns="1"
        :data="['filters' => $this->filters]"
        :widgets="$this->analyticsWidgets()"
    />
</x-filament-panels::page>
