@extends('layouts.public')

@section('title', 'Head Count / Telling')

@section('content')
    <style>
        .hc-form label { display: block; margin: 1.1rem 0 0.3rem; font-weight: 600; color: #374151; font-size: 0.95rem; }
        .hc-form select,
        .hc-form input[type="date"],
        .hc-form input[type="text"],
        .hc-form textarea {
            width: 100%; padding: 0.6rem 0.7rem; border: 1px solid #d1d5db;
            border-radius: 0.5rem; font-size: 1rem; box-sizing: border-box; font-family: inherit;
        }
        .hc-form textarea { min-height: 4.5rem; resize: vertical; }
        .hc-hint { color: #6b7280; font-size: 0.8rem; font-weight: 400; margin: 0.15rem 0 0; }

        .counter { display: flex; align-items: stretch; gap: 0.5rem; }
        .counter input {
            flex: 1; min-width: 0; text-align: center; font-size: 1.7rem; font-weight: 700;
            padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;
            -moz-appearance: textfield;
        }
        .counter input::-webkit-outer-spin-button,
        .counter input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .counter button {
            flex: 0 0 3.4rem; font-size: 1.7rem; font-weight: 700; line-height: 1;
            border: 1px solid #d1d5db; border-radius: 0.5rem; background: #f3f4f6; color: #374151; cursor: pointer;
        }
        .counter button:active { background: #e5e7eb; }

        .hc-form button[type="submit"] {
            margin-top: 1.75rem; width: 100%; padding: 0.85rem; border: 0; border-radius: 0.5rem;
            background: #4f46e5; color: #fff; font-size: 1.05rem; font-weight: 600; cursor: pointer;
        }
        .hc-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.25rem; }
        .hc-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    </style>

    <h1 class="page-title">Head Count / Telling</h1>
    <p class="page-subtitle">Count everyone in the meeting and submit the totals. / Tel iedereen in de bijeenkomst en dien de totalen in.</p>

    @if (session('success'))
        <div class="card" style="border-color:#16a34a;background:#f0fdf4;">
            <h3 style="color:#16a34a;">Head count submitted! / Telling ingediend!</h3>
            <p>Thank you. <a href="/count-heads">Submit another / Dien een andere in</a>.</p>
        </div>
    @endif

    <form method="POST" action="/count-heads" class="card hc-form">
        @csrf

        {{-- Honeypot: real people never see or fill this. --}}
        <div class="hc-hp" aria-hidden="true">
            <label for="company">Company</label>
            <input id="company" type="text" name="company" tabindex="-1" autocomplete="off">
        </div>

        <label for="group_id">Bacenta</label>
        <select id="group_id" name="group_id" required>
            <option value="" disabled {{ old('group_id') ? '' : 'selected' }}>Choose a bacenta… / Kies een bacenta…</option>
            @foreach ($bacentas as $bacenta)
                <option value="{{ $bacenta->id }}" {{ (string) old('group_id') === (string) $bacenta->id ? 'selected' : '' }}>{{ $bacenta->name }}</option>
            @endforeach
        </select>
        @error('group_id') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="date">Date / Datum</label>
        <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
        @error('date') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="total_attendance">Total present / Totaal aanwezig</label>
        <div class="counter">
            <button type="button" data-step="-1" aria-label="Decrease">&minus;</button>
            <input id="total_attendance" type="number" name="total_attendance" min="0" inputmode="numeric" value="{{ old('total_attendance') }}" required>
            <button type="button" data-step="1" aria-label="Increase">+</button>
        </div>
        @error('total_attendance') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="first_timer_count">First-timers / Eerste keer</label>
        <div class="counter">
            <button type="button" data-step="-1" aria-label="Decrease">&minus;</button>
            <input id="first_timer_count" type="number" name="first_timer_count" min="0" inputmode="numeric" value="{{ old('first_timer_count', 0) }}">
            <button type="button" data-step="1" aria-label="Increase">+</button>
        </div>
        @error('first_timer_count') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="visitor_count">Visitors / Bezoekers</label>
        <div class="counter">
            <button type="button" data-step="-1" aria-label="Decrease">&minus;</button>
            <input id="visitor_count" type="number" name="visitor_count" min="0" inputmode="numeric" value="{{ old('visitor_count', 0) }}">
            <button type="button" data-step="1" aria-label="Increase">+</button>
        </div>
        @error('visitor_count') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="submitter_name">Your name / Jouw naam</label>
        <input id="submitter_name" type="text" name="submitter_name" value="{{ old('submitter_name') }}" required>
        @error('submitter_name') <div class="hc-error">{{ $message }}</div> @enderror

        <label for="notes">Notes (optional) / Opmerkingen (optioneel)</label>
        <textarea id="notes" name="notes" maxlength="1000">{{ old('notes') }}</textarea>
        @error('notes') <div class="hc-error">{{ $message }}</div> @enderror

        <button type="submit">Submit head count / Telling indienen</button>
    </form>

    <script>
        document.querySelectorAll('.counter').forEach(function (counter) {
            var input = counter.querySelector('input');
            counter.querySelectorAll('button[data-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var step = parseInt(button.getAttribute('data-step'), 10);
                    var value = parseInt(input.value, 10);
                    if (isNaN(value)) { value = 0; }
                    value += step;
                    if (value < 0) { value = 0; }
                    input.value = value;
                });
            });
        });
    </script>
@endsection
