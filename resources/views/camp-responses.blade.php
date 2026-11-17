@extends('layouts.public')

@section('title', 'Registrations · '.$camps[$filters['camp']])

@section('head')
    <meta name="robots" content="noindex, nofollow">
    <style>
        .container { max-width: 76rem; }
        .resp-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
        .resp-head .page-title { margin-bottom: .15rem; }
        .resp-head .page-subtitle { margin-bottom: 0; }
        .resp-count { font-weight: 700; color: #0A1A3F; font-size: 1.05rem; }
        .resp-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(10rem, 100%), 1fr)); gap: .75rem; align-items: end; }
        .resp-filters > div { min-width: 0; }
        .resp-filters label { display: block; font-size: .8rem; font-weight: 600; color: #6b7280; margin-bottom: .25rem; }
        .resp-filters select, .resp-filters input { width: 100%; min-width: 0; max-width: 100%; padding: .5rem .6rem; border: 1px solid #d1d5db; border-radius: .5rem; font-size: .95rem; background: #fff; }
        .resp-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn { display: inline-block; padding: .55rem 1rem; border-radius: .5rem; font-weight: 600; font-size: .9rem; border: 0; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #0A1A3F; color: #fff; }
        .btn-primary:hover { background: #122a5e; text-decoration: none; }
        .btn-csv { background: #C8102E; color: #fff; }
        .btn-csv:hover { background: #a60d26; text-decoration: none; }
        .btn-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .btn-ghost:hover { text-decoration: none; background: #f3f4f6; }
        .resp-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; }
        .resp-table { width: 100%; border-collapse: collapse; font-size: .9rem; min-width: 60rem; }
        .resp-table th { text-align: left; background: #f8f9fb; color: #374151; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; padding: .7rem .75rem; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .resp-table td { padding: .65rem .75rem; border-bottom: 1px solid #f1f3f6; vertical-align: top; }
        .resp-table tr:last-child td { border-bottom: 0; }
        .resp-table td.num { text-align: center; }
        .resp-table .muted { color: #9ca3af; }
        .resp-table .yes { color: #15803d; font-weight: 600; }
        .resp-table .notes { max-width: 18rem; white-space: pre-wrap; }
        .btn-del { background: #fff; color: #b91c1c; border: 1px solid #fecaca; padding: .35rem .7rem; font-size: .8rem; }
        .btn-del:hover { background: #fef2f2; }
        .resp-flash { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: .7rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .resp-empty { padding: 2.5rem 1rem; text-align: center; color: #6b7280; }
        @media (max-width: 640px) { .resp-table { font-size: .85rem; } }
    </style>
@endsection

@section('content')
    @php
        $count = $rows->count();
        $query = array_filter(['camp' => $filters['camp'], 'church' => $filters['church'],
            'accommodation' => $filters['accommodation'], 'children' => $filters['children'], 'q' => $filters['q']],
            fn ($v) => $v !== null && $v !== '');
        $csvUrl = '/camp/responses/'.$key.'/csv'.($query ? '?'.http_build_query($query) : '');
    @endphp

    <div class="resp-head">
        <div>
            <h1 class="page-title">Camp registrations</h1>
            <p class="page-subtitle">{{ $camps[$filters['camp']] }}</p>
        </div>
        <div class="resp-count">{{ $count }} {{ \Illuminate\Support\Str::plural('registration', $count) }}</div>
    </div>

    @if (session('camp_deleted'))
        <div class="resp-flash">Removed {{ session('camp_deleted') }}.</div>
    @endif

    <form method="GET" action="/camp/responses/{{ $key }}" class="card">
        <div class="resp-filters">
            <div>
                <label for="camp">Camp</label>
                <select id="camp" name="camp">
                    @foreach ($camps as $slug => $label)
                        <option value="{{ $slug }}" @selected($filters['camp'] === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="church">Church</label>
                <select id="church" name="church">
                    <option value="">All churches</option>
                    @foreach ($churches as $c)
                        <option value="{{ $c }}" @selected($filters['church'] === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="accommodation">Needs accommodation</label>
                <select id="accommodation" name="accommodation">
                    <option value="">Any</option>
                    <option value="1" @selected($filters['accommodation'] === '1')>Yes</option>
                    <option value="0" @selected($filters['accommodation'] === '0')>No</option>
                </select>
            </div>
            <div>
                <label for="children">Bringing children</label>
                <select id="children" name="children">
                    <option value="">Any</option>
                    <option value="1" @selected($filters['children'] === '1')>Yes</option>
                    <option value="0" @selected($filters['children'] === '0')>No</option>
                </select>
            </div>
            <div>
                <label for="q">Search name or phone</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name or phone">
            </div>
            <div class="resp-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a class="btn btn-ghost" href="/camp/responses/{{ $key }}">Reset</a>
                <a class="btn btn-csv" href="{{ $csvUrl }}">Download CSV</a>
            </div>
        </div>
    </form>

    <div class="resp-table-wrap">
        @if ($count === 0)
            <div class="resp-empty">No registrations match these filters.</div>
        @else
            <table class="resp-table">
                <thead>
                    <tr>
                        <th>Registered</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Church</th>
                        <th>Travelling from</th>
                        <th>Accom.</th>
                        <th>Children</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td style="white-space:nowrap">{{ $r->created_at?->format('D j M, H:i') }}</td>
                            <td><strong>{{ $r->full_name }}</strong></td>
                            <td style="white-space:nowrap">{{ $r->phone_number }}</td>
                            <td>{{ $r->email ?: '' }}@if(!$r->email)<span class="muted">—</span>@endif</td>
                            <td>{{ $r->church_label ?: '' }}@if(!$r->church_label)<span class="muted">—</span>@endif</td>
                            <td>{{ $r->travelling_from ?: '' }}@if(!$r->travelling_from)<span class="muted">—</span>@endif</td>
                            <td class="num">@if($r->needs_accommodation)<span class="yes">Yes</span>@else<span class="muted">No</span>@endif</td>
                            <td class="num">@if($r->bringing_children)<span class="yes">Yes{{ $r->number_of_children !== null ? ' ('.$r->number_of_children.')' : '' }}</span>@else<span class="muted">No</span>@endif</td>
                            <td class="notes">{{ $r->notes ?: '' }}@if(!$r->notes)<span class="muted">—</span>@endif</td>
                            <td>
                                <form method="POST" action="/camp/responses/{{ $key }}/delete/{{ $r->id }}"
                                      onsubmit="return confirm(@js('Remove '.$r->full_name.' from the registrations? This cannot be undone.'))">
                                    @csrf
                                    <input type="hidden" name="back" value="{{ $query ? '?'.http_build_query($query) : '' }}">
                                    <button type="submit" class="btn btn-del">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
