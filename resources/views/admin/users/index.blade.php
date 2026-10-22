@extends('layouts.app')

@section('title', 'المستخدمون — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإدارة · المستخدمون</span>
@endsection

@section('content')
    <div class="panel-enter">
        <header class="ds-page-bar" aria-labelledby="users-heading">
            <div class="ds-page-bar-copy">
                <h1 id="users-heading" class="admin-title">المستخدمون</h1>
                <p class="admin-lead">إدارة حسابات المستخدمين الداخليين. دور واحد لكل مستخدم. التسجيل الذاتي غير متاح.</p>
            </div>
            <div class="ds-page-bar-tools">
                @can('users.manage')
                    <a href="{{ route('admin.users.create') }}" class="btn btn-glass-primary">+ مستخدم جديد</a>
                @endcan
            </div>
        </header>

        <div class="ds-table-surface">
            <div class="ds-data-body">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">الاسم</th>
                            <th scope="col">البريد</th>
                            <th scope="col">الدور</th>
                            <th scope="col">الحالة</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="ds-cell-muted dir-ltr">{{ $users->firstItem() + $loop->index }}</td>
                                <td class="ds-cell-primary">{{ $user->name }}</td>
                                <td class="dir-ltr">{{ $user->email }}</td>
                                <td>{{ $user->role?->name_ar ?? '—' }}</td>
                                <td>
                                    @if ($user->is_active)
                                        <span class="badge badge-success">نشط</span>
                                    @else
                                        <span class="badge badge-danger">موقوف</span>
                                    @endif
                                </td>
                                <td class="ds-cell-actions">
                                    @can('users.manage')
                                        <details class="ds-row-menu">
                                            <summary aria-label="إجراءات {{ $user->name }}">⋮</summary>
                                            <div class="ds-row-menu-panel">
                                                <a href="{{ route('admin.users.edit', $user) }}">تعديل</a>
                                            </div>
                                        </details>
                                    @else
                                        <span class="ds-cell-muted">—</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ds-data-empty">
                                        <strong>لا يوجد مستخدمون بعد</strong>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="ds-data-footer">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
