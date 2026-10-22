@extends('layouts.app')

@section('title', 'إعداد البريد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإعدادات · البريد</span>
@endsection

@section('content')
    <div class="mail-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="mail-heading">
            <div class="ds-page-bar-copy">
                <h1 id="mail-heading" class="admin-title">إعداد البريد الإلكتروني</h1>
                <p class="admin-lead">
                    عرض حالة إرسال البريد الحالية. لا يمكن تعديل إعدادات الاعتماد من هذه الشاشة،
                    ولا تُعرض كلمات المرور أو أسرار المزود.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('admin.settings.index') }}" class="btn btn-glass">رجوع للإعدادات</a>
            </div>
        </header>

        @if ($errors->has('mail_test'))
            <div class="app-alert app-alert-danger" role="alert">
                {{ $errors->first('mail_test') }}
            </div>
        @endif

        @if (session('mail_test_local_only'))
            <div class="app-alert app-alert-warning" role="status">
                <strong class="mail-alert-strong">نتيجة محلية فقط</strong>
                نجاح الاختبار هنا لا يعني وصول الرسالة إلى صندوق الوارد الفعلي.
            </div>
        @endif

        <div class="mail-status-banner mail-status-{{ $status->mode }}" role="status">
            <div class="mail-status-banner-top">
                <span class="mail-status-mark" aria-hidden="true">
                    @if ($status->mode === 'local')
                        ◌
                    @elseif ($status->mode === 'provider')
                        ✓
                    @else
                        !
                    @endif
                </span>
                <div class="mail-status-banner-meta">
                    @if ($status->mode === 'local')
                        <span class="mail-status-pill mail-status-pill-local">محلي فقط</span>
                    @elseif ($status->mode === 'provider')
                        <span class="mail-status-pill mail-status-pill-provider">جاهز للإرسال</span>
                    @else
                        <span class="mail-status-pill mail-status-pill-incomplete">غير مكتمل</span>
                    @endif
                </div>
            </div>
            <p class="mail-status-title">{{ $status->readinessLabelAr }}</p>
            <p class="mail-status-mode">{{ $status->modeLabelAr }}</p>
        </div>

        <div class="mail-section">
            <div class="mail-section-head">
                <h2 id="mail-config-title" class="mail-section-title">القيم الحالية (آمنة للعرض)</h2>
                <p class="mail-section-lead">القيم الحساسة مقنّعة ولا يمكن تعديلها من هنا.</p>
            </div>
            <div class="ds-table-surface mail-config-table-wrap">
                <div class="ds-data-body">
                    <table class="admin-table mail-config-table">
                        <thead>
                            <tr>
                                <th scope="col">البند</th>
                                <th scope="col">القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($status->rows as $row)
                                <tr @class(['is-sensitive' => ! empty($row['sensitive'])])>
                                    <td class="mail-config-label">
                                        {{ $row['label'] }}
                                        @if (! empty($row['sensitive']))
                                            <span class="mail-config-sensitive-tag">سرّ مقنّع</span>
                                        @endif
                                    </td>
                                    <td @class([
                                        'dir-ltr',
                                        'mail-config-value',
                                        'mail-sensitive' => ! empty($row['sensitive']),
                                    ])>{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <details class="mail-section mail-operator-details">
            <summary class="mail-section-title">تفاصيل للمشغّل</summary>
            <p class="mail-section-lead">
                المُرسِل الحالي: <span class="dir-ltr">{{ $status->mailer }}</span>.
                تغيير الإعدادات يتم في بيئة النشر فقط.
            </p>
            <ol class="mail-guidance">
                @foreach ($status->guidance as $line)
                    <li class="mail-guidance-item">
                        {!! preg_replace('/`([^`]+)`/', '<code>$1</code>', e($line)) !!}
                    </li>
                @endforeach
            </ol>
        </details>

        <section class="mail-test-panel" aria-labelledby="mail-test-heading">
            <div class="mail-test-panel-head">
                <p class="mail-test-kicker">حسابك فقط</p>
                <h2 id="mail-test-heading" class="mail-test-title">إرسال رسالة اختبار</h2>
                <p class="mail-test-lead">
                    تُرسل الرسالة إلى بريد حسابك الحالي فقط.
                </p>
            </div>

            <div class="mail-test-recipient" aria-label="المستلم">
                <span class="mail-test-recipient-label">المستلم (مقنّع)</span>
                <span class="mail-test-recipient-value dir-ltr">{{ $adminEmailMasked }}</span>
            </div>

            @if ($status->isLocalOnly)
                <p class="mail-test-local-note" role="note">
                    الإرسال الحالي للتجربة المحلية فقط.
                    نجاح الاختبار لا يعني وصول الرسالة إلى صندوق الوارد.
                </p>
            @endif

            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mail-test-form">
                @csrf
                <button type="submit" class="btn btn-glass-primary">
                    @if ($status->isLocalOnly)
                        تشغيل اختبار محلي
                    @else
                        إرسال اختبار إلى بريدي
                    @endif
                </button>
            </form>
        </section>
    </div>
@endsection
