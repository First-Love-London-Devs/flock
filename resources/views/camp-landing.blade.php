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
        .camp-hero .speaker { font-size: 1rem; color: #e8ecf7; }
        .camp-hero .flyer { max-width: 100%; border-radius: .75rem; margin: 1.5rem auto 0; display: block;
            box-shadow: 0 10px 30px -12px rgba(0,0,0,.6); }
        .camp-badges { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; margin: 1.5rem 0 .25rem; }
        .camp-badge { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
            padding: .45rem .85rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .camp-cta { display: inline-block; margin-top: 1.75rem; background: #C8102E; color: #fff;
            font-weight: 700; font-size: 1.05rem; padding: .9rem 2.25rem; border-radius: 999px; }
        .camp-cta:hover { background: #a60d26; text-decoration: none; }
        .camp-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .camp-facts .card h3 { color: #0A1A3F; }
        .camp-closed { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
            border-radius: .75rem; padding: 1rem 1.25rem; margin-top: 1.5rem; font-weight: 600; }
        @media (max-width: 560px) { .camp-facts { grid-template-columns: 1fr; } .camp-hero h1 { font-size: 1.7rem; } }
    </style>
@endsection

@section('content')
    @php
        $start = \Illuminate\Support\Carbon::parse($camp['start_date']);
        $end = \Illuminate\Support\Carbon::parse($camp['end_date']);
        $dateLabel = $start->format('j').'–'.$end->format('j F Y');
    @endphp

    <div class="camp-hero">
        <p class="eyebrow">First Love · Eurozone</p>
        <h1>{{ $camp['name'] }}</h1>
        <p class="speaker">with {{ $camp['speaker'] }}</p>
        <div class="camp-badges">
            <span class="camp-badge">{{ $dateLabel }}</span>
            <span class="camp-badge">{{ $camp['venue'] }}, Basel</span>
        </div>
        <img class="flyer" src="/images/camp-flyer.jpg" alt="{{ $camp['name'] }} flyer"
             onerror="this.style.display='none'">
        @unless ($closed)
            <a class="camp-cta" href="/camp/register">Register for camp</a>
        @endunless
    </div>

    @if ($closed)
        <div class="camp-closed">Registration is now closed. Thank you for your interest.</div>
    @endif

    <div class="section">
        <h2>The Camp</h2>
        <p>Join believers from across the Eurozone churches for the {{ $camp['name'] }}, ministered
           by {{ $camp['speaker'] }}. Five days in the presence of God &mdash; "The Lord is there."</p>
    </div>

    <div class="camp-facts">
        <div class="card">
            <h3>When</h3>
            <p>{{ $dateLabel }}<br>{{ $start->format('l') }} to {{ $end->format('l') }}</p>
        </div>
        <div class="card">
            <h3>Where</h3>
            <p>{{ $camp['venue'] }}<br>{{ $camp['address'] }}</p>
        </div>
    </div>

    @unless ($closed)
        <div style="text-align:center; margin-top: 1.5rem;">
            <a class="camp-cta" href="/camp/register">Register for camp</a>
        </div>
    @endunless
@endsection
