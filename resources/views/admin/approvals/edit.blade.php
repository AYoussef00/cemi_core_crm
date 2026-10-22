@extends('layouts.app')

@section('title', 'جهات الاعتماد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإدارة · جهات الاعتماد</span>
@endsection

@section('content')
    <div class="panel-enter">
        <header class="ds-page-bar" aria-labelledby="approvals-heading">
            <div class="ds-page-bar-copy">
                <h1 id="approvals-heading" class="admin-title">جهات الاعتماد</h1>
                <p class="admin-lead">
                    اختر الدور المسؤول عن كل مسار اعتماد. الافتراضي: مدير المبيعات لمعظم الاعتمادات،
                    والحسابات لمراجعة حد الائتمان. يلزم أن تكون لدى الدور المختار صلاحية الاعتماد المطلوبة.
                </p>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.approvals.update') }}" class="admin-form">
            @csrf
            @method('PUT')

            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">المسار</th>
                                <th scope="col">الدور المعيَّن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($authorities as $authority)
                                <tr>
                                    <td class="ds-cell-primary">{{ $authority->label_ar }}</td>
                                    <td>
                                        <select name="authorities[{{ $authority->id }}][assigned_role_id]" required>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected(old('authorities.'.$authority->id.'.assigned_role_id', $authority->assigned_role_id) == $role->id)>
                                                    {{ $role->name_ar }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('authorities.'.$authority->id.'.assigned_role_id')
                                            <p class="auth-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-glass-primary">حفظ جهات الاعتماد</button>
            </div>
        </form>
    </div>
@endsection
