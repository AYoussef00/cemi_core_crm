@extends('layouts.app')

@section('title', 'أمر استلام شحنة #'.$receipt->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المشتريات · أمر استلام شحنة</span>
@endsection

@section('content')
    <div class="procurement-page procurement-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="sgr-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">أمر استلام شحنة · <span class="dir-ltr">#{{ $receipt->id }}</span></p>
                <h1 id="sgr-heading" class="admin-title">استلام على أمر مورد #{{ $spo?->id }}</h1>
            </div>
            <div class="ds-page-bar-tools">
                @if ($spo)
                    <a href="{{ route('supplier-pos.show', $spo) }}" class="btn btn-glass">أمر المورد</a>
                @endif
                @if ($canPrint)
                    <a href="{{ route('supplier-goods-receipts.pdf', $receipt) }}" class="btn btn-glass" target="_blank" rel="noopener">PDF</a>
                @endif
            </div>
        </header>

        <section class="so-detail-section" aria-labelledby="sgr-summary-heading">
            <div class="so-detail-section-head">
                <h2 id="sgr-summary-heading" class="customers-form-section-title">ملخص الشحنة</h2>
            </div>
            <div class="so-detail-grid" role="list">
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">تاريخ الاستلام</p>
                    <p class="so-detail-value dir-ltr">
                        {{ $receipt->received_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">مرجع الشحنة</p>
                    <p class="so-detail-value">{{ $receipt->shipment_reference_ar ?: '—' }}</p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">المستلم</p>
                    <p class="so-detail-value">{{ $receipt->receivedBy?->name ?? '—' }}</p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">المورد</p>
                    <p class="so-detail-value">{{ $spo?->supplier_name_ar ?: '—' }}</p>
                </article>
                @if ($receipt->notes_ar)
                    <article class="so-detail-field" role="listitem" style="grid-column: 1 / -1;">
                        <p class="so-detail-label">ملاحظات</p>
                        <p class="so-detail-value">{{ $receipt->notes_ar }}</p>
                    </article>
                @endif
            </div>
        </section>

        @if ($receipt->attachments->isNotEmpty())
            <section class="so-detail-section" aria-labelledby="sgr-docs-heading">
                <div class="so-detail-section-head">
                    <h2 id="sgr-docs-heading" class="customers-form-section-title">مستند التسليم الموقع</h2>
                </div>
                <ul class="so-docs-list" style="list-style: none; padding: 0; margin: 0; display: grid; gap: 0.5rem;">
                    @foreach ($receipt->attachments as $att)
                        <li>
                            <a href="{{ route('supplier-goods-receipts.attachment', [$receipt, $att]) }}"
                               class="btn btn-glass btn-sm" target="_blank" rel="noopener">
                                {{ $att->original_name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="sgr-lines-heading">
            <div class="so-detail-section-head">
                <h2 id="sgr-lines-heading" class="customers-form-section-title">كميات هذه الشحنة</h2>
            </div>
            <div class="ds-list-body">
                <table class="admin-table admin-table--sheet">
                    <thead>
                        <tr>
                            <th scope="col">المنتج</th>
                            <th scope="col">الوحدة</th>
                            <th scope="col">الكمية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipt->lines as $line)
                            <tr>
                                <td><span class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</span></td>
                                <td>{{ $line->product?->unit?->name_ar ?? '—' }}</td>
                                <td class="dir-ltr">{{ qty($line->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
