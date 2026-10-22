<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell antialiased">
    <div class="auth-ambient" aria-hidden="true"></div>

    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:inset-e-3 focus:z-50 focus:rounded-full focus:bg-white/10 focus:px-3 focus:py-2">
        تخطي إلى المحتوى
    </a>

    <div class="auth-frame">
        <div class="auth-pane auth-pane-form">
            <header class="auth-top" role="banner">
                <a href="{{ route('login') }}" class="auth-brand" aria-label="{{ config('app.name') }}">
                    <span class="auth-brand-mark" aria-hidden="true">
                        <img src="{{ asset('branding/cemi-logo.png') }}" alt="" width="28" height="28" decoding="async">
                    </span>
                    <span class="auth-brand-name">{{ config('app.name') }}</span>
                </a>
            </header>

            <main id="main-content" class="auth-main" role="main">
                <div class="glass-panel auth-glass">
                    @if (session('status_ar'))
                        <div class="app-alert app-alert-success" role="status">{{ session('status_ar') }}</div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>

        <aside class="auth-pane auth-pane-visual" aria-hidden="true">
            <div class="auth-visual-orb auth-visual-orb-a"></div>
            <div class="auth-visual-orb auth-visual-orb-b"></div>
            <div class="auth-visual-orb auth-visual-orb-c"></div>
            <div class="auth-visual-glass">
                <p class="auth-visual-kicker">Cemi Core</p>
                <p class="auth-visual-line">عمليات تجارية داخلية بواجهة عربية دقيقة.</p>
            </div>
        </aside>
    </div>
</body>
</html>
