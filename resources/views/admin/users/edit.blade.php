@extends('layouts.app')

@section('title', 'تعديل مستخدم — '.config('app.name'))

@section('content')
    <section class="admin-panel panel-enter" aria-labelledby="edit-user-heading">
        <h1 id="edit-user-heading" class="admin-title">تعديل مستخدم</h1>
        <p class="admin-lead">دور واحد لكل مستخدم. لا تمنح صلاحيات اعتماد المبيعات لمدير النظام إلا عبر صلاحيات الدور المعتمدة.</p>

        @error('password_reset')
            <div class="app-alert app-alert-warning" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="auth-form admin-form" novalidate>
            @csrf
            @method('PUT')

            <div class="auth-field">
                <label for="name">الاسم</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                @error('email')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="role_id">الدور (واحد فقط)</label>
                <select id="role_id" name="role_id" required aria-invalid="{{ $errors->has('role_id') ? 'true' : 'false' }}">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name_ar }}</option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password">كلمة مرور جديدة (اختياري)</label>
                <input id="password" name="password" type="password" autocomplete="new-password"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                @error('password')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </div>

            <label class="auth-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                <span>الحساب نشط</span>
            </label>
            @error('is_active')
                <p class="auth-error" role="alert">{{ $message }}</p>
            @enderror

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">رجوع</a>
            </div>
        </form>

        <div class="admin-side-actions">
            @if ($user->is_active)
                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" onsubmit="return confirm('إيقاف هذا المستخدم؟');">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-danger-text">إيقاف الحساب</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">إعادة التفعيل</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">إرسال رابط إعادة تعيين كلمة المرور</button>
            </form>
        </div>
    </section>
@endsection
