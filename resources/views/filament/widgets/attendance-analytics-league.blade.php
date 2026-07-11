<x-filament::section>
    <x-slot name="heading">Per-stream league</x-slot>

    @php
        $rows = $this->getRows();
        $max = collect($rows)->max('total') ?: 1;
    @endphp

    @if (empty($rows))
        <p style="opacity:.7;margin:0">No attendance counted in this range yet.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;white-space:nowrap">
                <thead>
                    <tr style="text-align:left;opacity:.7">
                        <th style="padding:.4rem .6rem">#</th>
                        <th style="padding:.4rem .6rem">Stream</th>
                        <th style="padding:.4rem .6rem;text-align:right">First</th>
                        <th style="padding:.4rem .6rem;text-align:right">Returning</th>
                        <th style="padding:.4rem .6rem;text-align:right">Regular</th>
                        <th style="padding:.4rem .6rem;text-align:right">Visitor</th>
                        <th style="padding:.4rem .6rem;text-align:right">Services</th>
                        <th style="padding:.4rem .6rem;text-align:right">Avg</th>
                        <th style="padding:.4rem .6rem;text-align:right">Total</th>
                        <th style="padding:.4rem .6rem;width:26%">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr style="border-top:1px solid rgba(128,128,128,.2)">
                            <td style="padding:.5rem .6rem;opacity:.55">{{ $i + 1 }}</td>
                            <td style="padding:.5rem .6rem;font-weight:600">{{ $r['stream'] }}</td>
                            <td style="padding:.5rem .6rem;text-align:right">{{ number_format($r['first']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right">{{ number_format($r['returning']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right">{{ number_format($r['regular']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right">{{ number_format($r['visitor']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right;opacity:.7">{{ number_format($r['services']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right;opacity:.7">{{ number_format($r['average']) }}</td>
                            <td style="padding:.5rem .6rem;text-align:right;font-weight:700">{{ number_format($r['total']) }}</td>
                            <td style="padding:.5rem .6rem">
                                <div style="background:rgba(99,102,241,.15);border-radius:9999px;height:8px;width:100%">
                                    <div style="background:#6366f1;height:8px;border-radius:9999px;width:{{ $max ? round($r['total'] / $max * 100) : 0 }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
