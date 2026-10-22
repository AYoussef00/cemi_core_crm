@extends('layouts.auth')

@section('title', 'تسجيل الدخول — '.config('app.name'))

@section('content')
    <h1 class="auth-title">تسجيل الدخول</h1>
    <p class="auth-lead">للمستخدمين المعتمدين فقط. التسجيل الذاتي غير متاح.</p>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="email">البريد الإلكتروني</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                @if ($errors->has('email')) aria-describedby="email-error" @endif
            >
            @error('email')
                <p id="email-error" class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password">كلمة المرور</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                @if ($errors->has('password')) aria-describedby="password-error" @endif
            >
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
