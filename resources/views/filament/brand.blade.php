@php
    $logo = \App\Models\Setting::get('church_logo');
    $name = \App\Models\Setting::get('church_name', 'Flock');
@endphp

<div class="flex items-center gap-2">
    @if($logo)
        <img src="{{ $logo }}" alt="{{ $name }}" class="h-8" />
    @endif
    <span class="font-bold text-lg">{{ $name }}</span>
</div>
