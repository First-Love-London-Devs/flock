<x-filament-panels::page>
    @php $rows = $this->rows; @endphp

    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($rows->count()) }}</span>
            {{ Str::plural('person', $rows->count()) }} marked as a new convert in this window.
            @if ($rows->where('on_roll', 'Not on roll')->count())
                <span class="text-warning-600 dark:text-warning-400">
                    {{ number_format($rows->where('on_roll', 'Not on roll')->count()) }}
                    of them are not on the members roll yet.
                </span>
            @endif
            @if ($rows->where('on_roll', 'Welcome form')->count())
                {{-- Counted separately rather than folded into the line above:
                     somebody who filled in the welcome form may well already be
                     on the roll, so claiming they are not would be a guess. --}}
                <span class="text-info-600 dark:text-info-400">
                    {{ number_format($rows->where('on_roll', 'Welcome form')->count()) }}
                    came in through the welcome form.
                </span>
            @endif
        </p>

        <x-filament::button
            wire:click="export"
            icon="heroicon-o-arrow-down-tray"
            :disabled="$rows->isEmpty()"
        >
            Export to CSV
        </x-filament::button>
    </div>

    @if ($rows->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nobody was marked as a new convert between these dates. Widen the range, or check that
                bacenta leaders are ticking the new-convert box when they submit attendance.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4 font-semibold">Name</th>
                            <th class="py-2 pr-4 font-semibold">Phone</th>
                            <th class="py-2 pr-4 font-semibold">On roll</th>
                            <th class="py-2 pr-4 font-semibold">Group</th>
                            <th class="py-2 pr-4 font-semibold">First marked</th>
                            <th class="py-2 pr-4 font-semibold">Last marked</th>
                            <th class="py-2 pr-4 font-semibold text-right">Times</th>
                            <th class="py-2 font-semibold">NBS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $row['name'] }}</td>
                                <td class="py-2 pr-4 tabular-nums">{{ $row['phone'] ?: '—' }}</td>
                                {{--
                                    Three sources now, so this shows what the row
                                    actually says rather than testing for one value
                                    and calling everything else a member. When the
                                    welcome form was added, its people were being
                                    labelled "Member", which is the one thing they
                                    are known not to be.
                                --}}
                                <td class="py-2 pr-4">
                                    @if ($row['on_roll'] === 'Not on roll')
                                        <span class="text-warning-600 dark:text-warning-400">Not on roll</span>
                                    @elseif ($row['on_roll'] === 'Welcome form')
                                        <span class="text-info-600 dark:text-info-400">Welcome form</span>
                                    @else
                                        Member
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $row['group'] ?: '—' }}</td>
                                <td class="py-2 pr-4 tabular-nums">{{ $row['first_seen'] ?: '—' }}</td>
                                <td class="py-2 pr-4 tabular-nums">{{ $row['last_seen'] ?: '—' }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $row['times_marked'] }}</td>
                                <td class="py-2">{{ $row['nbs_status'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
