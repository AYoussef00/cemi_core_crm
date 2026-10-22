@extends('layouts.app')

@section('title', 'الأدوار والصلاحيات — '.config('app.name'))

@section('content')
    <section class="admin-panel panel-enter" aria-labelledby="roles-heading">
        <h1 id="roles-heading" class="admin-title">الأدوار والصلاحيات</h1>
        <p class="admin-lead">اختر الدور ثم حدّد ما يُسمح له به ونطاق كل صلاحية. مدير النظام لا يحصل على اعتمادات المبيعات تلقائيًا.</p>

        <form method="GET" action="{{ route('admin.roles.index') }}" class="admin-filter">
            <label for="role">الدور</label>
            <select id="role" name="role" onchange="this.form.submit()">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selected?->id === $role->id)>{{ $role->name_ar }}</option>
                @endforeach
            </select>
        </form>

        @if ($selected)
            <form method="POST" action="{{ route('admin.roles.update', $selected) }}" class="admin-form">
                @csrf
                @method('PUT')

                @foreach ($permissionGroups as $group => $permissions)
                    <fieldset class="admin-fieldset">
                        <legend>{{ match ((string) $group) {
                            'platform' => 'المنصة',
                            'customers' => 'العملاء',
                            'products' => 'المنتجات',
                            'sales' => 'المبيعات',
                            'procurement' => 'المشتريات',
                            'warehouse' => 'المخزون والمستودع',
                            'finance' => 'المالية',
                            'communications' => 'التواصل',
                            'reports' => 'التقارير',
                            default => 'الإدارة',
                        } }}</legend>
                        <div class="admin-perm-grid">
                            @foreach ($permissions as $permission)
                                @php
                                    $grant = $current[$permission->slug] ?? null;
                                    $enabled = (bool) $grant;
                                @endphp
                                <div class="admin-perm-row">
                                    <label class="auth-check">
                                        <input
                                            type="checkbox"
                                            name="grants[{{ $permission->slug }}][enabled]"
                                            value="1"
                                            @checked(old('grants.'.$permission->slug.'.enabled', $enabled))
                                        >
                                        <span>
                                            <strong>{{ $permission->name_ar }}</strong>
                                        </span>
                                    </label>
                                    <label class="admin-scope">
                                        <span class="sr-only">النطاق لـ {{ $permission->name_ar }}</span>
                                        <select name="grants[{{ $permission->slug }}][scope]">
                                            <option value="">بدون نطاق خاص</option>
                                            @foreach ($scopes as $scope)
                                                <option
                                                    value="{{ $scope->value }}"
                                                    @selected(old('grants.'.$permission->slug.'.scope', $grant['scope'] ?? null) === $scope->value)
                                                >
                                                    {{ $scope->labelAr() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-primary">حفظ الصلاحيات</button>
                </div>
            </form>
        @endif
    </section>
@endsection
