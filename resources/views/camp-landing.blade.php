@extends('layouts.public')

@section('title', $camp['name'])

@section('head')
    <style>
        .camp-hero {
            background: linear-gradient(160deg, #0A1A3F 0%, #122a5e 55%, #8f1020 100%);
            color: #fff; border-radius: 1rem; padding: 2.5rem 1.75rem; text-align: center;
            margin-bottom: 2rem; box-shadow: 0 20px 45px -25px rgba(10,26,63,.7);
        }
        .camp-hero .eyebrow { text-transform: uppercase; letter-spacing: .18em; font-size: .72rem;
            font-weight: 700; color: #ffd4d4; margin-bottom: .75rem; }
        .camp-hero h1 { font-size: 2.1rem; line-height: 1.12; font-weight: 800; margin-bottom: .6rem; }
        .camp-hero .churches { font-size: .95rem; color: #e8ecf7; max-width: 32rem; margin: 0 auto; }
        .camp-badges { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; margin: 1.5rem 0 .25rem; }
        .camp-badge { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
            padding: .45rem .85rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .camp-cta { display: inline-block; margin-top: 1.75rem; background: #C8102E; color: #fff;
            font-weight: 700; font-size: 1.05rem; padding: .9rem 2.25rem; border-radius: 999px; }
        .camp-cta:hover { background: #a60d26; text-decoration: none; }
        .camp-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .camp-facts .card h3 { color: #0A1A3F; }
        .camp-schedule { list-style: none; padding: 0; margin: 0; }
        .camp-schedule li { padding: .5rem 0; border-bottom: 1px solid #f1f3f6; }
        .camp-schedule li:last-child { border-bottom: 0; }
        .camp-schedule .day { display: block; font-weight: 600; color: #0A1A3F; font-size: .9rem; }
        .camp-schedule .what { color: #374151; font-size: .95rem; }
        .camp-schedule .when { color: #6b7280; }
        .camp-muted { color: #6b7280; }
        .camp-closed { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
            border-radius: .75rem; padding: 1rem 1.25rem; margin-top: 1.5rem; font-weight: 600; }
        @media (max-width: 560px) { .camp-facts { grid-template-columns: 1fr; } .camp-hero h1 { font-size: 1.7rem; } }
    </style>
@endsection

@section('content')
    @php
        $dateLabel = \App\Http\Controllers\Web\CampRegistrationController::dateLabel($camp, true);
        $oneDay = $camp['start_date'] === $camp['end_date'];
        $churchList = collect($churches);
        $churchSentence = $churchList->slice(0, -1)->implode(', ').' and '.$churchList->last();
    @endphp

    <div class="camp-hero">
        <p class="eyebrow">First Love · Eurozone</p>
        <h1>{{ $camp['name'] }}</h1>
        <p class="churches">{{ $churchSentence }}</p>
        <div class="camp-badges">
            <span class="camp-badge">{{ $dateLabel }}</span>
            <span class="camp-badge">{{ $camp['city'] }}</span>
        </div>
        @unless ($closed)
            <a class="camp-cta" href="/camp/register">Register for camp</a>
        @endunless
    </div>

    @if ($closed)
        <div class="camp-closed">Registration is now closed. Thank you for your interest.</div>
    @endif

    <div class="section">
        <h2>The Camp</h2>
        <p>Join believers from {{ $churchSentence }} for the {{ $camp['name'] }},
           {{ $dateLabel }} in {{ $camp['city'] }}, {{ $camp['country'] }}. {{ $oneDay ? 'A day together' : 'Two days together' }} in the presence of God.
           Campers arrive the evening before and travel home after the final session.</p>
    </div>

    <div class="camp-facts">
        <div class="card">
            <h3>Programme</h3>
            <ul class="camp-schedule">
                @foreach ($camp['schedule'] as $item)
                    <li>
                        <span class="day">{{ \Illuminate\Support\Carbon::parse($item['date'])->format('l j F') }}</span>
                        <span class="what">{{ $item['label'] }}@if (! empty($item['detail'])) <span class="when">· {{ $item['detail'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card">
            <h3>Where</h3>
            <p>{{ $camp['city'] }}, {{ $camp['country'] }}</p>
            @if ($camp['venue'])
                <p>{{ $camp['venue'] }}<br>{{ $camp['address'] }}</p>
            @else
                <p class="camp-muted">Venue details to follow.</p>
            @endif
        </div>
    </div>

    @unless ($closed)
        <div style="text-align:center; margin-top: 1.5rem;">
            <a class="camp-cta" href="/camp/register">Register for camp</a>
        </div>
    @endunless
@endsection
