<x-filament-panels::page>
    <form wire:submit="run">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                Run check
            </x-filament::button>
        </div>
    </form>

    @if ($this->report)
        @php
            $r = $this->report;
            $problems = count($r['unmatchedGroups'])
                + count($r['invalidEmail'])
                + count($r['missingName'])
                + count($r['dupEmailsInFile'])
                + count($r['invalidEnum'])
                + count($r['missingRequiredHeaders']);
        @endphp

        {{-- Verdict --}}
        @if ($problems === 0)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-success-500" />
                    <div>
                        <p class="text-lg font-semibold">Looks good to import</p>
                        <p class="text-sm text-gray-500">No blocking issues found in {{ number_format($r['total']) }} rows.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-8 w-8 text-warning-500" />
                    <div>
                        <p class="text-lg font-semibold">{{ $problems }} {{ \Illuminate\Support\Str::plural('issue', $problems) }} to review</p>
                        <p class="text-sm text-gray-500">Fix the items below before the final import. Nothing was saved.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Headline numbers --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                ['Total rows', $r['total'], 'gray'],
                ['Will be added', $r['willCreate'], 'success'],
                ['Will update existing', $r['willUpdate'], 'info'],
                ['No email', $r['blankEmail'], 'warning'],
            ] as [$label, $value, $color])
                <x-filament::section class="text-center">
                    <p @class([
                        'text-3xl font-bold',
                        'text-success-600' => $color === 'success',
                        'text-info-600' => $color === 'info',
                        'text-warning-600' => $color === 'warning',
                    ])>{{ number_format($value) }}</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Unmatched groups --}}
        @if (count($r['unmatchedGroups']))
            <x-filament::section icon="heroicon-o-x-circle" icon-color="danger">
                <x-slot name="heading">Groups that don't match a bacenta ({{ count($r['unmatchedGroups']) }})</x-slot>
                <x-slot name="description">These members would be imported <strong>without a group</strong> — they won't roll up to any gathering service. Fix the spelling in the sheet to match an existing bacenta exactly.</x-slot>
                <div class="space-y-1">
                    @foreach ($r['unmatchedGroups'] as $g)
                        <div class="flex items-center justify-between rounded-lg bg-danger-50 px-3 py-2 text-sm dark:bg-danger-400/10">
                            <span class="font-medium">{{ $g['name'] }}</span>
                            <x-filament::badge color="danger">{{ $g['count'] }} {{ \Illuminate\Support\Str::plural('member', $g['count']) }}</x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Matched groups → gathering service --}}
        @php $matched = collect($r['groups'])->where('matched', true); @endphp
        @if ($matched->count())
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success" collapsible>
                <x-slot name="heading">Where members will land ({{ $matched->count() }} groups)</x-slot>
                <x-slot name="description">Each matched group and the gathering service it rolls up to.</x-slot>
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2">Group (bacenta)</th>
                                <th class="px-3 py-2">Gathering service</th>
                                <th class="px-3 py-2 text-right">Members</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($matched as $g)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $g['name'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $g['gs'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $g['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Duplicate emails in file --}}
        @if (count($r['dupEmailsInFile']))
            <x-filament::section icon="heroicon-o-document-duplicate" icon-color="warning">
                <x-slot name="heading">Duplicate emails in the file ({{ count($r['dupEmailsInFile']) }})</x-slot>
                <x-slot name="description">An email can only exist once in Flock. If these are different people, give each their own email (or leave one blank).</x-slot>
                <div class="space-y-1">
                    @foreach ($r['dupEmailsInFile'] as $email => $lines)
                        <div class="flex items-center justify-between rounded-lg bg-warning-50 px-3 py-2 text-sm dark:bg-warning-400/10">
                            <span class="font-mono">{{ $email }}</span>
                            <span class="text-gray-500">rows {{ implode(', ', $lines) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Invalid emails --}}
        @if (count($r['invalidEmail']))
            <x-filament::section icon="heroicon-o-envelope" icon-color="warning">
                <x-slot name="heading">Emails that don't look valid ({{ count($r['invalidEmail']) }})</x-slot>
                <div class="space-y-1">
                    @foreach ($r['invalidEmail'] as $row)
                        <div class="flex items-center justify-between rounded-lg bg-warning-50 px-3 py-2 text-sm dark:bg-warning-400/10">
                            <span class="font-mono">{{ $row['value'] }}</span>
                            <span class="text-gray-500">row {{ $row['line'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Missing names --}}
        @if (count($r['missingName']))
            <x-filament::section icon="heroicon-o-user-minus" icon-color="danger">
                <x-slot name="heading">Rows missing a first or last name ({{ count($r['missingName']) }})</x-slot>
                <x-slot name="description">These rows will be rejected — first and last name are required.</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">Rows: {{ implode(', ', $r['missingName']) }}</p>
            </x-filament::section>
        @endif

        {{-- Invalid enum values --}}
        @if (count($r['invalidEnum']))
            <x-filament::section icon="heroicon-o-list-bullet" icon-color="warning">
                <x-slot name="heading">Unexpected dropdown values ({{ count($r['invalidEnum']) }})</x-slot>
                <x-slot name="description">gender must be male/female/other · member_type must be member/visitor/first_timer/new_convert · nbs_status must be not_started/in_progress/completed.</x-slot>
                <div class="space-y-1">
                    @foreach ($r['invalidEnum'] as $row)
                        <div class="flex items-center justify-between rounded-lg bg-warning-50 px-3 py-2 text-sm dark:bg-warning-400/10">
                            <span><span class="font-mono">{{ $row['field'] }}</span> = "{{ $row['value'] }}"</span>
                            <span class="text-gray-500">row {{ $row['line'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Header / structural notes --}}
        @if (count($r['missingRequiredHeaders']) || count($r['unknownHeaders']) || $r['blankEmail'] || $r['blankMatchExisting'])
            <x-filament::section icon="heroicon-o-information-circle" collapsible collapsed>
                <x-slot name="heading">Notes</x-slot>
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    @if (count($r['missingRequiredHeaders']))
                        <li class="text-danger-600">Missing required column(s): <strong>{{ implode(', ', $r['missingRequiredHeaders']) }}</strong>.</li>
                    @endif
                    @if (count($r['unknownHeaders']))
                        <li>Columns that will be ignored on import: {{ implode(', ', $r['unknownHeaders']) }}.</li>
                    @endif
                    @if ($r['blankEmail'])
                        <li>{{ $r['blankEmail'] }} row(s) have no email. They're matched by name + phone, so importing the same sheet twice is safe — but check for genuine duplicates.</li>
                    @endif
                    @if ($r['blankMatchExisting'])
                        <li>{{ $r['blankMatchExisting'] }} no-email row(s) already match an existing member by name/phone — those will <strong>update</strong> rather than add.</li>
                    @endif
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
