@extends('layouts.app')

@section('title', 'سجل التدقيق — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">سجل التدقيق</span>
@endsection

@section('content')
    @php
        $filtersActive = filled($filters['action'])
            || filled($filters['actor_user_id'])
            || filled($filters['date_from'])
            || filled($filters['date_to']);
    @endphp

    <div class="audit-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="audit-heading">
            <div class="ds-page-bar-copy">
                <h1 id="audit-heading" class="admin-title">سجل التدقيق</h1>
                <p class="admin-lead">
                    سجل بالإجراءات المهمة على الأمان والإعدادات، للاطلاع فقط.
                    لا تُعرض كلمات المرور ولا الأسرار.
                    @if ($filtersActive)
                        <span class="badge badge-info">تصفية نشطة</span>
                    @endif
                </p>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.audit.index') }}" class="ds-filter-bar audit-filters" aria-label="تصفية سجل التدقيق">
            <div class="ds-filter-bar-fields audit-filters-grid">
                <div class="auth-field">
                    <label for="action">الإجراء</label>
                    <select id="action" name="action">
                        <option value="">الكل</option>
                        @foreach ($actions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-field">
                    <label for="actor_user_id">المُنفِّذ</label>
                    <select id="actor_user_id" name="actor_user_id">
                        <option value="">الكل</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) $filters['actor_user_id'] === (string) $actor->id)>
                                {{ $actor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-field">
                    <label for="date_from">من تاريخ</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                </div>

                <div class="auth-field">
                    <label for="date_to">إلى تاريخ</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                </div>
            </div>

            <div class="ds-filter-bar-actions audit-filter-actions">
                <button type="submit" class="btn btn-glass-primary">تطبيق</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-glass">إعادة تعيين</a>
            </div>
        </form>

        <div class="ds-table-surface">
            <div class="ds-data-body">
                <table class="admin-table audit-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">الوقت</th>
                            <th scope="col">الإجراء</th>
                            <th scope="col">المُنفِّذ</th>
                            <th scope="col">الموضوع</th>
                            <th scope="col">التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="ds-cell-muted dir-ltr">{{ $logs->firstItem() + $loop->index }}</td>
                                <td class="audit-time">
                                    <time datetime="{{ $log->created_at?->toIso8601String() }}">
                                        {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                    </time>
                                </td>
                                <td>
                                    <div class="audit-action">
                                        <span class="audit-action-label">{{ $log->actionLabelAr() }}</span>
                                        <code class="audit-action-key dir-ltr">{{ $log->action }}</code>
                                    </div>
                                </td>
                                <td>
                                    @if ($log->actor)
                                        <div class="audit-actor">
                                            <span class="audit-actor-name ds-cell-primary">{{ $log->actor->name }}</span>
                                            <span class="dir-ltr muted">{{ $log->actor->email }}</span>
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->subject_type)
                                        <span class="audit-subject dir-ltr">
                                            <span class="audit-subject-type">{{ class_basename($log->subject_type) }}</span>
                                            <span class="audit-subject-id">#{{ $log->subject_id }}</span>
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! empty($log->properties))
                                        <details class="audit-details">
                                            <summary>عرض التفاصيل</summary>
                                            <pre class="audit-json dir-ltr" tabindex="0">{{ json_encode($log->properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ds-data-empty" role="status">
                                        @if ($filtersActive)
                                            <strong>لا نتائج لهذه التصفية</strong>
                                            <p>جرّب توسيع الفترة أو إعادة تعيين التصفية. السجل للاطلاع فقط.</p>
                                            <a href="{{ route('admin.audit.index') }}" class="btn btn-glass btn-sm">إعادة تعيين التصفية</a>
                                        @else
                                            <strong>لا توجد أحداث بعد</strong>
                                            <p>ستظهر هنا الإجراءات المهمة عند حدوثها. لا تُعرض كلمات المرور ولا الأسرار.</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="ds-data-footer">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
