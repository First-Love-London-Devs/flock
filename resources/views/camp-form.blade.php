@extends('layouts.public')

@section('title', 'Register · '.$camp['name'])

@section('head')
    <style>
        .camp-form label { display: block; margin: 0.9rem 0 0.25rem; font-weight: 600; color: #374151; font-size: 0.95rem; }
        .camp-form input[type="text"], .camp-form input[type="tel"], .camp-form input[type="email"],
        .camp-form input[type="number"], .camp-form select, .camp-form textarea {
            width: 100%; padding: 0.6rem 0.7rem; border: 1px solid #d1d5db;
            border-radius: 0.5rem; font-size: 1rem; box-sizing: border-box; background: #fff;
        }
        .camp-form textarea { min-height: 4.5rem; resize: vertical; }
        .camp-form fieldset { border: 1px solid #e5e7eb; border-radius: 0.5rem; margin: 1rem 0; padding: 0.75rem 1rem; }
        .camp-form legend { font-weight: 600; color: #374151; font-size: 0.95rem; padding: 0 0.4rem; }
        .camp-form .choices label { display: inline-flex; align-items: center; gap: 0.4rem;
            margin: 0.2rem 1.25rem 0.2rem 0; font-weight: 400; }
        .camp-form .optional { color: #9ca3af; font-weight: 400; }
        .camp-form button { margin-top: 1.5rem; width: 100%; padding: 0.85rem; border: 0; border-radius: 0.5rem;
            background: #C8102E; color: #fff; font-size: 1.05rem; font-weight: 700; cursor: pointer; }
        .camp-form button:hover { background: #a60d26; }
        .camp-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.2rem; }
        .camp-hide { display: none; }
    </style>
@endsection

@section('content')
    <h1 class="page-title">Register for camp</h1>
    <p class="page-subtitle">{{ $camp['name'] }} · {{ \Illuminate\Support\Carbon::parse($camp['start_date'])->format('j') }}–{{ \Illuminate\Support\Carbon::parse($camp['end_date'])->format('j F') }} · {{ $camp['venue'] }}, Basel</p>

    @if (session('success'))
        <div class="card" style="border-color:#16a34a;background:#f0fdf4;">
            <h3 style="color:#16a34a;">You're registered! 🙌</h3>
            <p>We've received your registration for the {{ $camp['name'] }}. See you there.</p>
        </div>
        <p style="margin-top:1rem;"><a href="/camp/register">Register someone else</a></p>
    @elseif ($closed)
        <div class="card" style="border-color:#fed7aa;background:#fff7ed;">
            <h3 style="color:#9a3412;">Registration closed</h3>
            <p>Registration for the camp is now closed. Thank you for your interest.</p>
        </div>
    @else
        <form method="POST" action="/camp/register" class="card camp-form">
            @csrf

            <label for="full_name">Full name</label>
            <input id="full_name" type="text" name="full_name" value="{{ old('full_name') }}" required>
            @error('full_name') <div class="camp-error">{{ $message }}</div> @enderror

            <label for="phone_number">Phone number</label>
            <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" required>
            @error('phone_number') <div class="camp-error">{{ $message }}</div> @enderror

            <label for="email">Email <span class="optional">(optional)</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}">
            @error('email') <div class="camp-error">{{ $message }}</div> @enderror

            <label for="country">Country</label>
            <select id="country" name="country" required>
                <option value="">Select your country</option>
                @foreach ($countries as $c)
                    <option value="{{ $c }}" @selected(old('country') === $c)>{{ $c }}</option>
                @endforeach
            </select>
            @error('country') <div class="camp-error">{{ $message }}</div> @enderror

            <label for="denomination">Denomination</label>
            <select id="denomination" name="denomination" required>
                <option value="">Select your denomination</option>
                @foreach ($denominations as $d)
                    <option value="{{ $d }}" @selected(old('denomination') === $d)>{{ $d }}</option>
                @endforeach
            </select>
            @error('denomination') <div class="camp-error">{{ $message }}</div> @enderror

            <label for="travelling_from">Travelling from <span class="optional">(city / country, optional)</span></label>
            <input id="travelling_from" type="text" name="travelling_from" value="{{ old('travelling_from') }}">
            @error('travelling_from') <div class="camp-error">{{ $message }}</div> @enderror

            <fieldset class="choices">
                <legend>Do you need help with accommodation?</legend>
                <label><input type="radio" name="needs_accommodation" value="1" @checked(old('needs_accommodation') === '1') required> Yes</label>
                <label><input type="radio" name="needs_accommodation" value="0" @checked(old('needs_accommodation', '0') === '0')> No</label>
                @error('needs_accommodation') <div class="camp-error">{{ $message }}</div> @enderror
            </fieldset>

            <fieldset class="choices">
                <legend>Are you bringing children?</legend>
                <label><input type="radio" name="bringing_children" value="1" @checked(old('bringing_children') === '1') required> Yes</label>
                <label><input type="radio" name="bringing_children" value="0" @checked(old('bringing_children', '0') === '0')> No</label>
                @error('bringing_children') <div class="camp-error">{{ $message }}</div> @enderror
            </fieldset>

            <div id="children_count" class="camp-hide">
                <label for="number_of_children">How many children?</label>
                <input id="number_of_children" type="number" name="number_of_children" min="0" max="50" value="{{ old('number_of_children') }}">
                @error('number_of_children') <div class="camp-error">{{ $message }}</div> @enderror
            </div>

            <label for="notes">Anything we should know? <span class="optional">(dietary needs, etc. — optional)</span></label>
            <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            @error('notes') <div class="camp-error">{{ $message }}</div> @enderror

            <button type="submit">Register</button>
        </form>

        <script>
            (function () {
                var bringing = document.querySelectorAll('input[name="bringing_children"]');
                var count = document.getElementById('children_count');
                function toggleCount() {
                    var yes = document.querySelector('input[name="bringing_children"]:checked');
                    count.className = (yes && yes.value === '1') ? '' : 'camp-hide';
                }
                bringing.forEach(function (r) { r.addEventListener('change', toggleCount); });
                toggleCount();
            })();
        </script>
    @endif
@endsection
