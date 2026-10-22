@extends('layouts.app')

@section('title', config('app.name').' — الرئيسية')

@section('page_heading')
    <span class="topbar-muted">الرئيسية</span>
@endsection

@section('content')
    <div class="dash-home dash-overview panel-enter">
        @if ($dashboard['is_empty'])
            <section class="ds-data-empty ds-data-empty-surface dash-home-empty" aria-label="لا توجد بيانات" role="status">
                <strong>لا توجد بيانات لعرضها</strong>
                <p class="muted">ستظهر هنا مؤشرات عملك فور توفر سجلات ضمن صلاحياتك. الصفر حقيقي — ليست بيانات معاينة.</p>
            </section>
        @else
            <section class="dash-ov-kpis" aria-label="المؤشرات الرئيسية">
                @foreach ($dashboard['kpis'] as $index => $kpi)
                    <a href="{{ $kpi['href'] }}" class="dash-ov-kpi dash-ov-kpi--{{ $kpi['tone'] }} {{ $index === 0 ? 'is-featured' : '' }}">
                        <div class="dash-ov-kpi-main">
                            <div class="dash-ov-kpi-copy">
                                <span class="dash-ov-kpi-label">{{ $kpi['label'] }}</span>
                                <span class="dash-ov-kpi-value">{{ number_format($kpi['value']) }}</span>
                                @if (! empty($kpi['subvalue']))
                                    <span class="dash-ov-kpi-sub">{{ $kpi['subvalue'] }}</span>
                                @endif
                                @if ($kpi['chips'] !== [])
                                    <span class="dash-ov-kpi-chips">
                                        @foreach ($kpi['chips'] as $chip)
                                            <span class="dash-ov-chip dash-ov-chip--{{ $chip['tone'] }}">{{ $chip['text'] }}</span>
                                        @endforeach
                                    </span>
                                @elseif (empty($kpi['subvalue']))
                                    <span class="dash-ov-kpi-hint">ضمن نطاق صلاحيتك</span>
                                @endif
                            </div>
                            <span class="dash-ov-kpi-visual" aria-hidden="true">
                                @include('foundation.partials.dash-kpi-visual', ['tone' => $kpi['tone'], 'key' => $kpi['key']])
                            </span>
                        </div>
                        <span class="dash-ov-kpi-more">عرض المزيد</span>
                    </a>
                @endforeach
            </section>

            @if ($dashboard['charts'] !== [])
                <section class="dash-ov-charts dash-charts" aria-label="التحليلات">
                    @foreach ($dashboard['charts'] as $chart)
                        @include('foundation.partials.dash-chart', ['chart' => $chart])
                    @endforeach
                </section>
            @endif
        @endif
    </div>
@endsection
