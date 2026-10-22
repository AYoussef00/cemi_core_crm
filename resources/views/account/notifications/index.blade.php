@extends('layouts.app')

@section('title', 'الإشعارات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الحساب · الإشعارات</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter notifications-panel" aria-labelledby="notifications-heading">
        <div class="admin-header">
            <div>
                <p class="page-kicker">داخل التطبيق فقط · لحسابك</p>
                <h1 id="notifications-heading" class="admin-title">الإشعارات</h1>
                <p class="admin-lead">
إشعارات حسابك داخل التطبيق فقط. لا تُرسل هذه الشاشة بريدًا إلكترونيًا.
                </p>
                @if ($unreadCount > 0)
                    <p class="notifications-status" role="status">
                        <span class="notifications-unread-summary">{{ $unreadCount }} غير مقروء</span>
                    </p>
                @elseif ($notifications->total() > 0)
                    <p class="notifications-status" role="status">
                        <span class="notifications-read-summary">الكل مقروء</span>
                    </p>
                @endif
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('account.notifications.read-all') }}" class="notifications-bulk">
                    @csrf
                    <button type="submit" class="btn btn-glass-primary">تعليم الكل كمقروء</button>
                </form>
            @endif
        </div>

        <div class="notifications-list" role="list">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $title = $data['title_ar'] ?? 'إشعار';
                    $body = $data['body_ar'] ?? null;
                    $actionUrl = $data['action_url'] ?? null;
                    $category = \App\Support\Ui\NotificationCategory::labelAr($data['category'] ?? null);
                    $isUnread = $notification->read_at === null;
                @endphp
                <article
                    class="notification-card {{ $isUnread ? 'is-unread' : 'is-read' }}"
                    role="listitem"
                    @if ($isUnread) aria-label="غير مقروء: {{ $title }}" @endif
                >
                    <div class="notification-card-indicator" aria-hidden="true"></div>
                    <div class="notification-card-main">
                        <div class="notification-card-meta">
                            @if ($isUnread)
                                <span class="notification-badge">جديد</span>
                            @else
                                <span class="notification-badge is-read-badge">مقروء</span>
                            @endif
                            @if ($category)
                                <span class="notification-category">{{ $category }}</span>
                            @endif
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}" class="muted">
                                {{ $notification->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </time>
                        </div>
                        <h2 class="notification-card-title">{{ $title }}</h2>
                        @if ($body)
                            <p class="notification-card-body">{{ $body }}</p>
                        @endif
                        @if ($actionUrl && str_starts_with($actionUrl, '/') && ! str_starts_with($actionUrl, '//'))
                            <a href="{{ $actionUrl }}" class="notification-card-link">عرض التفاصيل</a>
                        @endif
                    </div>
                    @if ($isUnread)
                        <form method="POST" action="{{ route('account.notifications.read', $notification->id) }}" class="notification-card-actions">
                            @csrf
                            <button type="submit" class="btn btn-glass btn-sm">تعليم كمقروء</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="notifications-empty" role="status">
                    <strong>لا توجد إشعارات</strong>
                    <p class="muted">
                        ستظهر هنا إشعاراتك عند وصولها.
                    </p>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="admin-pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
@endsection
