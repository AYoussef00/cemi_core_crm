@extends('layouts.app')

@section('title', 'الحساب — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الحساب</span>
@endsection

@section('content')
    <section class="content-card" aria-labelledby="profile-heading">
        <h1 id="profile-heading" class="auth-title">حسابي</h1>
        <p class="auth-lead">بياناتك الأساسية وتغيير كلمة المرور. الأدوار والصلاحيات يديرها مسؤول النظام.</p>

        <dl class="profile-meta">
            <div>
                <dt>الاسم</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt>البريد الإلكتروني</dt>
                <dd>{{ $user->email }}</dd>
            </div>
        </dl>

        <h2 class="auth-subtitle">تغيير كلمة المرور</h2>

        <form method="POST" action="{{ route('account.password') }}" class="auth-form" novalidate>
            @csrf
            @method('PUT')

            <div class="auth-field">
                <label for="current_password">كلمة المرور الحالية</label>
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                    aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                    @if ($errors->has('current_password')) aria-describedby="current-password-error" @endif
                >
                @error('current_password')
                    <p id="current-password-error" class="auth-error" role="alert">{{ $message }}</p>
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
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    @if ($errors->has('password')) aria-describedby="password-error" @endif
                >
                @error('password')
                    <p id="password-error" class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-glass-primary auth-submit">تحديث كلمة المرور</button>
        </form>
    </section>
@endsection
