@extends('layouts.auth')

@section('title', 'استعادة كلمة المرور — '.config('app.name'))

@section('content')
    <p class="auth-kicker">استعادة الحساب</p>
    <h1 class="auth-title">استعادة كلمة المرور</h1>
    <p class="auth-lead">أدخل بريدك المسجّل لإرسال رابط إعادة التعيين. <a href="{{ route('login') }}">العودة لتسجيل الدخول</a></p>

    @unless ($mailConfigured)
        <div class="app-alert app-alert-warning" role="status">
            <strong>إرسال البريد غير متاح حاليًا.</strong>
            قد لا يصلك رابط الاستعادة. تواصل مع مسؤول النظام لإكمال إعداد البريد.
        </div>
    @endunless

    <form method="POST" action="{{ route('password.email') }}" class="auth-form" novalidate>
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

        <button type="submit" class="btn btn-glass-primary auth-submit">إرسال رابط الاستعادة</button>
    </form>
@endsection
