@php
    $logoDark = \App\Models\Setting::get('church_logo_dark');
    $logo = \App\Models\Setting::get('church_logo');
    $name = \App\Models\Setting::get('church_name', 'Flock');
    $displayLogo = $logoDark ?: $logo;
@endphp

<div class="flex items-center gap-2">
    @if($displayLogo)
        <img src="{{ $displayLogo }}" alt="{{ $name }}" class="h-8" />
    @endif
    <span class="font-bold text-lg">{{ $name }}</span>
</div>
