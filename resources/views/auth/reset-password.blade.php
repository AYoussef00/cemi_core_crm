@extends('layouts.auth')

@section('title', 'تعيين كلمة مرور جديدة — '.config('app.name'))

@section('content')
    <p class="auth-kicker">كلمة مرور جديدة</p>
    <h1 class="auth-title">تعيين كلمة مرور جديدة</h1>
    <p class="auth-lead">يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل، وحروف كبيرة وصغيرة، ورقم.</p>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="auth-field">
            <label for="email">البريد الإلكتروني</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                autocomplete="username"
                required
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                @if ($errors->has('email')) aria-describedby="email-error" @endif
            >
            @error('email')
                <p id="email-error" class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password">كلمة المرور الجديدة</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
                autofocus
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                @if ($errors->has('password')) aria-describedby="password-error" @endif
            >
            @error('password')
                <p id="password-error" class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-glass-primary auth-submit">حفظ كلمة المرور</button>
    </form>

    <p class="auth-footer-link">
        <a href="{{ route('login') }}">العودة لتسجيل الدخول</a>
    </p>
@endsection
