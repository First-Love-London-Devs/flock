@extends('layouts.public')

@section('title', 'Attendance Counter / Aanwezigheidsteller')

@section('content')
    <h1 class="page-title">Attendance Counter / Aanwezigheidsteller</h1>
    <p class="page-subtitle">Choose a service to start counting. / Kies een dienst om te beginnen met tellen.</p>

    @forelse ($streams as $stream)
        <a href="/attendance-counter/{{ \Illuminate\Support\Str::slug($stream->name) }}" class="card" style="display:block;">
            <h3>{{ $stream->name }}</h3>
            <p>Open counter / Open teller &rarr;</p>
        </a>
    @empty
        <div class="card">
            <p>No services are set up yet. / Er zijn nog geen diensten ingesteld.</p>
        </div>
    @endforelse
@endsection
