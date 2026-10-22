@switch($name)
    @case('quotations')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5A1.5 1.5 0 0 1 7.5 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3.5V8h4M9 12.5h6M9 16h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('customer_pos')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m8.5 12 2.4 2.4L15.5 9.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('sales_orders')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M6 7v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        @break
    @case('visits')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="4" y="5.5" width="16" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M4 10h16M8.5 3.5v4M15.5 3.5v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        @break
    @case('sales_requests')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 4.5h8a1.5 1.5 0 0 1 1.5 1.5v13l-3-1.5-3 1.5-3-1.5-3 1.5V6A1.5 1.5 0 0 1 8 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.5 9.5h5M9.5 13h3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        @break
    @case('preparation')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4.5 8.5h15v10.5a1.5 1.5 0 0 1-1.5 1.5h-12a1.5 1.5 0 0 1-1.5-1.5V8.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 8.5V6.5A4 4 0 0 1 12 2.5a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        @break
    @case('invoices')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5A1.5 1.5 0 0 1 7.5 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 12h6M9 15.5h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        @break
    @case('deals')
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3.5 20 8v8l-8 4.5L4 16V8l8-4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 12v8.5M12 12 20 8M12 12 4 8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
        @break
@endswitch
