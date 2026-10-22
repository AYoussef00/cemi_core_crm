@extends('layouts.app')

@section('title', 'كشوف العملاء — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">التقارير · الكشوف</span>
@endsection

@section('content')
    <div class="reports-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="stmt-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تقارير · {{ $timezone }}</p>
                <h1 id="stmt-heading" class="admin-title">كشوف حساب العملاء</h1>
                <p class="admin-lead">
                    اختر منشأة لعرض فواتيرها والمتبقي وفق CR7. بلا تصدير ملفات ولا جدولة في v1.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('reports.ar.index') }}" class="btn btn-glass">تقرير الذمم</a>
            </div>
        </header>

        @if ($customers->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا منشآت ضمن نطاقك</strong>
                <p>الكشف يظهر فقط للعملاء المسموح بعرضهم. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">المنشأة</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="ds-cell-primary">{{ $customer->name_ar }}</td>
                                    <td class="ds-cell-actions">
                                        <a href="{{ route('reports.statements.show', $customer) }}" class="btn btn-glass btn-sm">عرض الكشف</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($customers->hasPages())
                    <div class="ds-data-footer">{{ $customers->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
