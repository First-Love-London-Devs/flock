@extends('layouts.public')

@section('title', 'Welcome / Welkom')

@section('content')
    <style>
        .uc-streams { list-style: none; padding: 0; margin: 1rem 0 0; }
        .uc-streams a {
            display: block; padding: 1rem 1.25rem; margin-bottom: 0.75rem;
            border: 1px solid #d1d5db; border-radius: 0.6rem; text-decoration: none;
            color: #1f2937; font-weight: 600; font-size: 1.05rem; background: #fff;
        }
        .uc-streams a:hover { border-color: #4f46e5; }
    </style>

    <h1 class="page-title">Welcome / Welkom</h1>
    <p class="page-subtitle">Please choose your service to continue. / Kies je dienst om verder te gaan.</p>

    @if ($streams->isEmpty())
        <div class="card"><p>No services are available yet. / Er zijn nog geen diensten beschikbaar.</p></div>
    @else
        <ul class="uc-streams">
            @foreach ($streams as $stream)
                <li><a href="/welcome/{{ \Illuminate\Support\Str::slug($stream->name) }}">{{ $stream->name }} →</a></li>
            @endforeach
        </ul>
    @endif
@endsection
