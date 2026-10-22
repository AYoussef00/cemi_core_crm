{{-- Floating alert room — notifications only, no chat input. Auth users. --}}
@auth
<div
    id="alert-room"
    class="alert-room is-open"
    data-feed-url="{{ route('account.notifications.feed') }}"
    data-read-all-url="{{ route('account.notifications.read-all') }}"
    data-read-url-template="{{ url('/account/notifications/__ID__/read') }}"
    data-csrf="{{ csrf_token() }}"
    data-poll-ms="20000"
    data-initial-unread="{{ (int) $shellUnread }}"
>
    <div
        id="alert-room-panel"
        class="alert-room-panel"
        role="dialog"
        aria-modal="false"
        aria-labelledby="alert-room-title"
    >
        <header class="alert-room-head">
            <div class="alert-room-head-copy">
                <p class="alert-room-kicker">غرفة التنبيهات</p>
                <h2 id="alert-room-title" class="alert-room-title">الإشعارات</h2>
            </div>
            <div class="alert-room-head-actions">
                <button type="button" class="alert-room-icon-btn" id="alert-room-mute" aria-pressed="false" title="معاينة صوت التنبيه" aria-label="معاينة صوت التنبيه">
                    <svg class="alert-room-ico-sound" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10v4h3l5 4V6L7 10H4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M16 9.5a3.5 3.5 0 0 1 0 5M18.5 7a6 6 0 0 1 0 10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    <svg class="alert-room-ico-mute" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" hidden><path d="M4 10v4h3l5 4V6L7 10H4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m16 10 4 4m0-4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
                <button type="button" class="alert-room-icon-btn" id="alert-room-close" title="تصغير" aria-label="تصغير غرفة التنبيهات">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 12h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
            </div>
        </header>

        <div class="alert-room-toolbar">
            <span class="alert-room-unread" id="alert-room-unread" role="status">{{ (int) $shellUnread }} غير مقروء</span>
            <button type="button" class="btn btn-glass btn-sm" id="alert-room-read-all" @disabled($shellUnread < 1)>تعليم الكل كمقروء</button>
        </div>

        <div class="alert-room-stream" id="alert-room-stream" role="log" aria-live="polite" aria-relevant="additions">
            <div class="alert-room-loading" id="alert-room-loading">جاري التحميل…</div>
        </div>

        <footer class="alert-room-foot">
            <a href="{{ route('account.notifications.index') }}" class="alert-room-foot-link">عرض كل الإشعارات</a>
        </footer>
    </div>

    <button
        type="button"
        id="alert-room-fab"
        class="alert-room-fab"
        aria-expanded="true"
        aria-controls="alert-room-panel"
        title="غرفة التنبيهات"
        aria-label="فتح غرفة التنبيهات{{ $shellUnread > 0 ? '، '.$shellUnread.' غير مقروء' : '' }}"
    >
        <span class="alert-room-fab-glow" aria-hidden="true"></span>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v6A2.5 2.5 0 0 1 16.5 16H10l-3.8 2.7A.8.8 0 0 1 5 18V7.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M8.5 10h7M8.5 13h4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
        <span class="alert-room-fab-badge" id="alert-room-fab-badge" @if ($shellUnread < 1) hidden @endif>{{ min((int) $shellUnread, 99) }}</span>
    </button>
</div>
@endauth
