{{-- Decorative KPI glyph only — not a data chart. --}}
@php
    $tone = $tone ?? 'primary';
@endphp
<span class="dash-ov-visual dash-ov-visual--{{ $tone }}">
    @switch($key ?? '')
        @case('invoices')
            <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <circle cx="32" cy="32" r="22" stroke="currentColor" stroke-width="6" opacity="0.18"/>
                <circle cx="32" cy="32" r="22" stroke="currentColor" stroke-width="6"
                        stroke-dasharray="88 140" stroke-linecap="round" transform="rotate(-90 32 32)"/>
            </svg>
            @break
        @case('sales_orders')
        @case('preparation')
            <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <rect x="10" y="36" width="8" height="18" rx="2" fill="currentColor" opacity="0.35"/>
                <rect x="24" y="24" width="8" height="30" rx="2" fill="currentColor" opacity="0.55"/>
                <rect x="38" y="14" width="8" height="40" rx="2" fill="currentColor"/>
                <rect x="52" y="28" width="8" height="26" rx="2" fill="currentColor" opacity="0.45"/>
            </svg>
            @break
        @case('visits')
            <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <path d="M8 44 C16 28, 24 48, 32 34 C40 20, 48 40, 56 22" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>
                <circle cx="56" cy="22" r="4" fill="currentColor"/>
            </svg>
            @break
        @default
            <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <rect x="12" y="38" width="10" height="14" rx="2" fill="currentColor" opacity="0.35"/>
                <rect x="27" y="28" width="10" height="24" rx="2" fill="currentColor" opacity="0.55"/>
                <rect x="42" y="16" width="10" height="36" rx="2" fill="currentColor"/>
            </svg>
    @endswitch
</span>
