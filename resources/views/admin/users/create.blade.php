@extends('layouts.app')

@section('title', 'مستخدم جديد — '.config('app.name'))

@section('content')
    <section class="admin-panel panel-enter" aria-labelledby="create-user-heading">
        <h1 id="create-user-heading" class="admin-title">مستخدم جديد</h1>
        <p class="admin-lead">اختر دورًا واحدًا للمستخدم. لا يمكن للمستخدم التسجيل بنفسه.</p>

        <form method="POST" action="{{ route('admin.users.store') }}" class="auth-form admin-form" novalidate>
            @csrf

            <div class="auth-field">
                <label for="name">الاسم</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       autocomplete="off" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                @error('email')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="role_id">الدور (واحد فقط)</label>
                <select id="role_id" name="role_id" required aria-invalid="{{ $errors->has('role_id') ? 'true' : 'false' }}">
                    <option value="">— اختر دورًا —</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name_ar }}</option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password">كلمة المرور المؤقتة</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                @error('password')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password_confirmation">تأكيد كلمة المرور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>

            <label class="auth-check">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <span>الحساب نشط</span>
            </label>

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary">إنشاء المستخدم</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </section>
@endsection
