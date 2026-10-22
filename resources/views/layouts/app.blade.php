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
<body class="app-shell antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:inset-e-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">
        تخطي إلى المحتوى
    </a>

    @php
        $shellCompany = null;
        try {
            $shellCompany = \App\Models\Company::current();
        } catch (\Throwable) {
            $shellCompany = null;
        }

        $shellUnread = 0;
        if (auth()->check()) {
            try {
                $shellUnread = auth()->user()->unreadNotifications()->count();
            } catch (\Throwable) {
                $shellUnread = 0;
            }
        }
    @endphp

    <div class="app-frame">
        {{-- Sidebar first in DOM so dir=rtl places it on the right. Always visible, full labels. --}}
        @include('layouts.partials.app-sidebar')

        <div class="app-workspace">
            <header class="app-topbar">
                <div class="topbar-title-slot">
                    @hasSection('page_heading')
                        @yield('page_heading')
                    @else
                        <span class="topbar-muted">{{ config('app.name') }}</span>
                    @endif
                </div>
                @auth
                    <a
                        href="{{ route('account.notifications.index') }}"
                        class="topbar-notify"
                        title="الإشعارات"
                        aria-label="الإشعارات{{ $shellUnread > 0 ? '، '.$shellUnread.' غير مقروء' : '' }}"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 3.2 1.2 4.5 1.2 4.5H4.8S6 12.2 6 9Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M10 18a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        @if ($shellUnread > 0)
                            <span class="topbar-notify-dot" aria-hidden="true"></span>
                        @endif
                    </a>
                    <a href="{{ route('account.profile') }}" class="topbar-avatar" title="الحساب">{{ mb_substr(auth()->user()->name, 0, 1) }}</a>
                @endauth
            </header>

            <main id="main-content" class="app-main" role="main">
                @if (session('status_ar'))
                    <div class="app-alert app-alert-success" role="status">{{ session('status_ar') }}</div>
                @endif
                @yield('content')
            </main>

        </div>
    </div>

    @include('layouts.partials.alert-room')

    @stack('scripts')
</body>
</html>
