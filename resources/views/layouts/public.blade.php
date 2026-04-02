<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') - Flock</title>
        <link rel="icon" type="image/png" href="/images/flock-logo.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Inter', sans-serif; color: #1a1a2e; background: #f8f9fb; line-height: 1.6; }
            a { color: #4f46e5; text-decoration: none; }
            a:hover { text-decoration: underline; }

            .nav { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
            .nav-brand { display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem; font-weight: 700; color: #1a1a2e; text-decoration: none; }
            .nav-brand:hover { text-decoration: none; }
            .nav-brand img { width: 32px; height: 32px; object-fit: contain; }
            .nav-links { display: flex; gap: 1.5rem; }
            .nav-links a { color: #6b7280; font-size: 0.875rem; font-weight: 500; }
            .nav-links a:hover { color: #4f46e5; text-decoration: none; }

            .container { max-width: 48rem; margin: 0 auto; padding: 3rem 1.5rem; }
            .page-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
            .page-subtitle { color: #6b7280; margin-bottom: 2.5rem; font-size: 1.05rem; }

            .section { margin-bottom: 2rem; }
            .section h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; color: #1a1a2e; }
            .section p, .section li { color: #374151; font-size: 0.95rem; line-height: 1.7; }
            .section ul { padding-left: 1.25rem; list-style: disc; }
            .section li { margin-bottom: 0.35rem; }

            .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
            .card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
            .card p { color: #374151; font-size: 0.95rem; }

            .footer { border-top: 1px solid #e5e7eb; padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.8rem; margin-top: 3rem; }
            .footer a { color: #9ca3af; }
            .footer a:hover { color: #4f46e5; }
            .footer-links { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 0.75rem; }

            @media (max-width: 640px) {
                .container { padding: 2rem 1rem; }
                .page-title { font-size: 1.5rem; }
                .nav { padding: 1rem; }
                .nav-links { gap: 1rem; }
            }
        </style>
        @yield('head')
    </head>
    <body>
        <nav class="nav">
            <a href="/" class="nav-brand"><img src="/images/flock-logo.png" alt="Flock logo">Flock</a>
            <div class="nav-links">
                <a href="/support">Support</a>
                <a href="/privacy">Privacy</a>
            </div>
        </nav>

        <div class="container">
            @yield('content')
        </div>

        <footer class="footer">
            <div class="footer-links">
                <a href="/">Home</a>
                <a href="/support">Support</a>
                <a href="/privacy">Privacy Policy</a>
            </div>
            <p>&copy; {{ date('Y') }} Church Stack. All rights reserved.</p>
        </footer>
    </body>
</html>
