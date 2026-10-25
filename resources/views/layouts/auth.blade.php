<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:inset-e-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">
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
                @if (session('status_ar'))
                    <div class="app-alert app-alert-success" role="status">{{ session('status_ar') }}</div>
                @endif
                @yield('content')
            </main>
        </div>

        <aside class="auth-pane auth-pane-visual" aria-hidden="true">
            <div class="auth-visual-hex auth-visual-hex-a"></div>
            <div class="auth-visual-hex auth-visual-hex-b"></div>
            <div class="auth-visual-hex auth-visual-hex-c"></div>

            <div class="auth-preview">
                <div class="auth-preview-window">
                    <div class="auth-preview-sidebar">
                        <span class="auth-preview-mark"></span>
                        <span></span>
                        <span></span>
                        <span class="is-on"></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="auth-preview-body">
                        <div class="auth-preview-toolbar">
                            <span></span>
                            <span></span>
                        </div>
                        <svg class="auth-preview-chart" viewBox="0 0 360 140" fill="none" aria-hidden="true">
                            <path d="M8 108 C 70 108, 90 42, 150 58 C 210 74, 230 22, 292 38 C 320 46, 338 70, 352 86" stroke="#2f6fed" stroke-width="3" stroke-linecap="round"/>
                            <path d="M8 108 C 70 108, 90 42, 150 58 C 210 74, 230 22, 292 38 C 320 46, 338 70, 352 86 L 352 140 L 8 140 Z" fill="url(#authChartFill)"/>
                            <defs>
                                <linearGradient id="authChartFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#2f6fed" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#2f6fed" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="auth-preview-pills">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
                <div class="auth-preview-badge">
                    <img src="{{ asset('branding/cemi-logo.png') }}" alt="" width="28" height="28" decoding="async">
                </div>
                <div class="auth-preview-card">
                    <span class="auth-preview-card-dot"></span>
                    <svg viewBox="0 0 120 36" fill="none" aria-hidden="true">
                        <path d="M2 28 C 22 28, 28 10, 48 14 C 68 18, 78 6, 98 10 C 108 12, 114 18, 118 22" stroke="#2f6fed" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>

            <div class="auth-visual-copy">
                <p class="auth-visual-headline">أسهل طريقة لإدارة عملياتك التجارية.</p>
                <p class="auth-visual-line">مبيعات، مخزون، وتسليمات في واجهة عربية واحدة للفريق الداخلي.</p>
            </div>
        </aside>
    </div>
</body>
</html>
