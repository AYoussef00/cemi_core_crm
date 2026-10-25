@extends('layouts.auth')

@section('title', 'تسجيل الدخول — '.config('app.name'))

@section('content')
    <h1 class="auth-title">سجّل الدخول إلى حسابك.</h1>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label class="visually-hidden" for="email">البريد الإلكتروني</label>
            <div class="auth-field-icon">
                <span class="auth-field-ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5.5" width="17" height="13" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="m4.5 7.5 7.5 5.2L19.5 7.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                    placeholder="البريد الإلكتروني"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    @if ($errors->has('email')) aria-describedby="email-error" @endif
                >
            </div>
            @error('email')
                <p id="email-error" class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label class="visually-hidden" for="password">كلمة المرور</label>
            <div class="auth-field-icon">
                <span class="auth-field-ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="5" y="10.5" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10.5V8.2a4 4 0 0 1 8 0v2.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    placeholder="كلمة المرور"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    @if ($errors->has('password')) aria-describedby="password-error" @endif
                >
                <button
                    type="button"
                    class="auth-field-toggle"
                    data-auth-password-toggle
                    aria-label="إظهار كلمة المرور"
                    aria-pressed="false"
                >
                    <svg class="auth-eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12S6.2 6.5 12 6.5 21.2 12 21.2 12 17.8 17.5 12 17.5 2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.7"/></svg>
                    <svg class="auth-eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" hidden><path d="M3 3l18 18M9.5 9.6A3 3 0 0 0 14.4 14.5M7 7.4C5 8.6 3.5 10.5 2.8 12S6.2 17.5 12 17.5c1.6 0 3-.3 4.3-.8M16.7 7.2C15.3 6.7 13.7 6.5 12 6.5c-5.8 0-9.2 5.5-9.2 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
            </div>
            @error('password')
                <p id="password-error" class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <label class="auth-check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>تذكرني على هذا الجهاز</span>
        </label>

        <button type="submit" class="btn btn-glass-primary auth-submit">تسجيل الدخول</button>
    </form>
@endsection
