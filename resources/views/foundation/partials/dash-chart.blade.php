@if ($chart['type'] === 'bars')
    <section class="dash-status-block" aria-label="{{ $chart['title'] }}">
        <header class="dash-status-block-head">
            <h2 class="dash-section-title">{{ $chart['title'] }}</h2>
            <span class="dash-ov-chart-tag">{{ $chart['unit'] }}</span>
        </header>
        <div class="dash-ov-kpis">
            @foreach ($chart['segments'] as $segment)
                <div class="dash-ov-kpi dash-ov-kpi--{{ $segment['tone'] }}">
                    <div class="dash-ov-kpi-main">
                        <div class="dash-ov-kpi-copy">
                            <span class="dash-ov-kpi-label">{{ $segment['label'] }}</span>
                            <span class="dash-ov-kpi-value">{{ number_format($segment['value']) }}</span>
                            <span class="dash-ov-kpi-hint">{{ $chart['unit'] }}</span>
                        </div>
                        <span class="dash-ov-kpi-visual" aria-hidden="true">
                            @include('foundation.partials.dash-kpi-visual', ['tone' => $segment['tone'], 'key' => 'sales_orders'])
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@else
    <article class="dash-chart dash-ov-chart">
        <header class="dash-ov-chart-head">
            <h2 class="dash-section-title">{{ $chart['title'] }}</h2>
            <span class="dash-ov-chart-tag">{{ $chart['unit'] }}</span>
        </header>

        @if ($chart['type'] === 'donut')
            @php
                $total = max($chart['total'], 1);
                $offset = 25;
            @endphp
            <div class="dash-donut">
                <div class="dash-donut-figure">
                    <svg viewBox="0 0 42 42" role="img" aria-label="{{ $chart['title'] }}">
                        <circle class="dash-donut-track" cx="21" cy="21" r="15.9155" fill="none" stroke-width="5"/>
                        @foreach ($chart['segments'] as $segment)
                            @php
                                $pct = ($segment['value'] / $total) * 100;
                                $gap = count($chart['segments']) > 1 ? 0.6 : 0;
                            @endphp
                            <circle
                                class="dash-tone-stroke dash-tone-{{ $segment['tone'] }}"
                                cx="21" cy="21" r="15.9155" fill="none" stroke-width="5"
                                stroke-dasharray="{{ round(max($pct - $gap, 0.1), 2) }} {{ round(100 - max($pct - $gap, 0.1), 2) }}"
                                stroke-dashoffset="{{ round($offset, 2) }}"/>
                            @php $offset -= $pct; @endphp
                        @endforeach
                    </svg>
                    <span class="dash-donut-center">
                        <strong>{{ number_format($chart['total']) }}</strong>
                        <small>{{ $chart['unit'] }}</small>
                    </span>
                </div>

                <ul class="dash-legend">
                    @foreach ($chart['segments'] as $segment)
                        <li>
                            <span class="dash-swatch dash-tone-bg dash-tone-{{ $segment['tone'] }}" aria-hidden="true"></span>
                            <span class="dash-legend-label">{{ $segment['label'] }}</span>
                            <strong>{{ number_format($segment['value']) }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($chart['type'] === 'columns')
            <div class="dash-columns dash-ov-columns" role="img" aria-label="{{ $chart['title'] }}">
                @foreach ($chart['columns'] as $column)
                    <div class="dash-column {{ $column['current'] ? 'is-current' : '' }}">
                        <span class="dash-column-value">{{ $column['value'] > 0 ? number_format($column['value']) : '' }}</span>
                        <span class="dash-column-bar-wrap">
                            <span class="dash-column-bar" style="height: {{ $column['value'] > 0 ? max(8, round(($column['value'] / $chart['max']) * 100)) : 3 }}%"></span>
                        </span>
                        <span class="dash-column-label">{{ $column['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <footer class="dash-ov-chart-foot dash-ov-chart-foot--single">
                <div class="dash-ov-chart-stat">
                    <span class="dash-ov-chart-stat-dot" style="background:#0f766e" aria-hidden="true"></span>
                    <div>
                        <span class="dash-ov-chart-stat-label">الإجمالي في الفترة</span>
                        <strong class="dash-ov-chart-stat-value">{{ number_format(array_sum(array_column($chart['columns'], 'value'))) }} {{ $chart['unit'] }}</strong>
                    </div>
                </div>
            </footer>
        @endif
    </article>
@endif
