@extends('layouts.public')

@section('title', 'Attendance Counter / Aanwezigheidsteller')

@section('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
    <style>
        .ac-badge {
            display: inline-block; margin-bottom: 1.25rem; padding: 0.4rem 1rem; border-radius: 999px;
            background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-weight: 600; font-size: 0.95rem;
        }
        .ac-flash {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50; text-align: center;
            background: #10b981; color: #fff; font-weight: 700; font-size: 1.1rem; padding: 1rem;
            transform: translateY(-100%); transition: transform 0.25s ease;
        }
        .ac-flash.show { transform: translateY(0); }

        .ac-buttons { display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1.5rem; }
        .ac-btn {
            width: 100%; border: 0; border-radius: 1.25rem; color: #fff; cursor: pointer;
            padding: 3rem 1.5rem; font-size: 1.6rem; font-weight: 700; line-height: 1.25;
            box-shadow: 0 12px 24px rgba(0,0,0,0.12); transition: transform 0.08s ease;
        }
        .ac-btn:active { transform: translateY(2px); }
        .ac-btn small { display: block; font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin-top: 0.35rem; }
        .ac-btn.first-time { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .ac-btn.returning  { background: linear-gradient(135deg, #10b981, #059669); }
        .ac-btn.regular    { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .ac-btn.visitor    { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .ac-actions { margin-top: 1.75rem; text-align: center; }
        .ac-show {
            border: 1px solid #d1d5db; background: #fff; color: #374151; border-radius: 0.5rem;
            padding: 0.6rem 1.1rem; font-size: 0.95rem; font-weight: 600; cursor: pointer;
        }

        .ac-counts { margin-top: 1.5rem; display: none; }
        .ac-counts.show { display: block; }
        .ac-counts .row { display: flex; justify-content: space-between; padding: 0.65rem 0; border-bottom: 1px solid #f0f0f4; font-size: 1rem; }
        .ac-counts .row.total {
            margin-top: 0.5rem; border: 0; border-radius: 0.6rem; padding: 0.85rem 1rem; font-weight: 700; font-size: 1.15rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff;
        }

        @media (max-width: 640px) {
            .ac-btn { padding: 2.4rem 1.25rem; font-size: 1.4rem; }
        }
    </style>

    <div class="ac-flash" data-flash aria-live="polite">Counted! Thank you! / Geteld! Bedankt!</div>

    <span class="ac-badge">{{ $stream->name }}</span>
    <h1 class="page-title">Welcome! / Welkom!</h1>
    <p class="page-subtitle">Please tap the option that best describes you. / Tik op de optie die het beste bij je past.</p>

    <div class="ac-buttons">
        <button type="button" class="ac-btn first-time" onclick="increment('first_time')">
            First time here <small>Eerste keer hier</small>
        </button>
        <button type="button" class="ac-btn returning" onclick="increment('returning')">
            I've been here before <small>Ik ben hier eerder geweest</small>
        </button>
        <button type="button" class="ac-btn regular" onclick="increment('regular')">
            Regular attendee <small>Vaste bezoeker</small>
        </button>
        <button type="button" class="ac-btn visitor" onclick="increment('visitor')">
            Visitor from another church <small>Bezoeker van een andere kerk</small>
        </button>
    </div>

    <div class="ac-actions">
        <button type="button" class="ac-show" onclick="toggleCounts()">Show current count / Toon huidige telling</button>
    </div>

    <div class="ac-counts" data-counts>
        <div class="row"><span>First time / Eerste keer</span><span data-count="first_time">0</span></div>
        <div class="row"><span>Been here before / Eerder geweest</span><span data-count="returning">0</span></div>
        <div class="row"><span>Regular / Vaste bezoeker</span><span data-count="regular">0</span></div>
        <div class="row"><span>Visitor / Bezoeker</span><span data-count="visitor">0</span></div>
        <div class="row total"><span>Total / Totaal</span><span data-count="total">0</span></div>
    </div>

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var base = '/attendance-counter/' + @json($streamSlug);
            var flash = document.querySelector('[data-flash]');
            var countsPanel = document.querySelector('[data-counts]');
            var flashTimer = null;

            function deviceId() {
                var id = localStorage.getItem('attendance_device_id');
                if (!id) {
                    id = 'device_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('attendance_device_id', id);
                }
                return id;
            }

            function showFlash() {
                flash.classList.add('show');
                if (flashTimer) { clearTimeout(flashTimer); }
                flashTimer = setTimeout(function () { flash.classList.remove('show'); }, 2500);
            }

            function paint(counts) {
                Object.keys(counts).forEach(function (key) {
                    var el = countsPanel.querySelector('[data-count="' + key + '"]');
                    if (el) { el.textContent = counts[key]; }
                });
            }

            window.increment = function (category) {
                fetch(base + '/increment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ category: category, device_id: deviceId() })
                }).then(function (res) {
                    if (!res.ok) { throw new Error('bad status'); }
                    return res.json();
                }).then(function (data) {
                    showFlash();
                    if (data.counts) { paint(data.counts); }
                }).catch(function () {
                    alert('Could not record that tap, please try again. / Kon deze telling niet opslaan, probeer opnieuw.');
                });
            };

            window.toggleCounts = function () {
                if (countsPanel.classList.contains('show')) {
                    countsPanel.classList.remove('show');
                    return;
                }
                fetch(base + '/counts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({})
                }).then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.counts) { paint(data.counts); }
                        countsPanel.classList.add('show');
                    });
            };
        })();
    </script>
@endsection
