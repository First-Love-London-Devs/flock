@extends('layouts.public')

@section('title', 'Welcome / Welkom')

@section('content')
    <style>
        .uc-form label { display: block; margin: 0.9rem 0 0.25rem; font-weight: 600; color: #374151; font-size: 0.95rem; }
        .uc-form input[type="text"],
        .uc-form input[type="tel"],
        .uc-form input[type="date"] {
            width: 100%; padding: 0.6rem 0.7rem; border: 1px solid #d1d5db;
            border-radius: 0.5rem; font-size: 1rem; box-sizing: border-box;
        }
        .uc-form fieldset { border: 1px solid #e5e7eb; border-radius: 0.5rem; margin: 1rem 0; padding: 0.75rem 1rem; }
        .uc-form legend { font-weight: 600; color: #374151; font-size: 0.95rem; padding: 0 0.4rem; }
        .uc-form fieldset label { display: inline-flex; align-items: center; gap: 0.35rem; margin-right: 1.25rem; font-weight: 400; }
        .uc-form button { margin-top: 1.5rem; width: 100%; padding: 0.8rem; border: 0; border-radius: 0.5rem;
            background: #4f46e5; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .uc-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.2rem; }
    </style>

    <h1 class="page-title">Welcome / Welkom</h1>
    <p class="page-subtitle">{{ $stream->name }} — we'd love to get to know you. / {{ $stream->name }} — we willen je graag leren kennen. Vul hieronder je gegevens in.</p>

    @if (session('success'))
        <div class="card" style="border-color:#16a34a;background:#f0fdf4;">
            <h3 style="color:#16a34a;">Thank you! / Bedankt!</h3>
            <p>We've received your details and someone will be in touch soon. / We hebben je gegevens ontvangen en nemen binnenkort contact met je op.</p>
        </div>
    @endif

    <form method="POST" action="/welcome/{{ $streamSlug }}" class="card uc-form">
        @csrf

        <label for="attended_on">Date</label>
        <input id="attended_on" type="date" name="attended_on" value="{{ old('attended_on', now()->toDateString()) }}" required>
        @error('attended_on') <div class="uc-error">{{ $message }}</div> @enderror

        <label for="first_name">First Name / Voornaam</label>
        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required>
        @error('first_name') <div class="uc-error">{{ $message }}</div> @enderror

        <label for="last_name">Surname / Achternaam</label>
        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required>
        @error('last_name') <div class="uc-error">{{ $message }}</div> @enderror

        <label for="street_name">Street Name / Straatnaam</label>
        <input id="street_name" type="text" name="street_name" value="{{ old('street_name') }}" required>
        @error('street_name') <div class="uc-error">{{ $message }}</div> @enderror

        <label for="postal_code">Postal Code / Postcode</label>
        <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code') }}" required>
        @error('postal_code') <div class="uc-error">{{ $message }}</div> @enderror

        <label for="phone_number">Phone Number / Telefoonnummer</label>
        <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" required>
        @error('phone_number') <div class="uc-error">{{ $message }}</div> @enderror

        <fieldset>
            <legend>Are you re-dedicating your life to Christ? / Geef je jouw leven opnieuw aan Jezus?</legend>
            <label><input type="radio" name="re_dedicating" value="1" {{ old('re_dedicating') === '1' ? 'checked' : '' }} required> Yes / Ja</label>
            <label><input type="radio" name="re_dedicating" value="0" {{ old('re_dedicating') === '0' ? 'checked' : '' }}> No / Nee</label>
            @error('re_dedicating') <div class="uc-error">{{ $message }}</div> @enderror
        </fieldset>

        <fieldset>
            <legend>Is this your first time attending this church? / Is het jouw eerste keer in deze kerk?</legend>
            <label><input type="radio" name="first_time" value="1" {{ old('first_time') === '1' ? 'checked' : '' }} required> Yes / Ja</label>
            <label><input type="radio" name="first_time" value="0" {{ old('first_time') === '0' ? 'checked' : '' }}> No / Nee</label>
            @error('first_time') <div class="uc-error">{{ $message }}</div> @enderror
        </fieldset>

        <label for="who_invited">Who invited you? / Wie heeft jou uitgenodigd?</label>
        <input id="who_invited" type="text" name="who_invited" value="{{ old('who_invited') }}" required>
        @error('who_invited') <div class="uc-error">{{ $message }}</div> @enderror

        <button type="submit">Submit / Verstuur</button>
    </form>
@endsection
