{{-- Shared company brand header for official documents (quote / delivery note / invoice). --}}
@php
    $title = $title ?? '';
    $company = $company ?? \App\Models\Company::current();
@endphp
<header class="doc-brand-header">
    <div class="doc-brand-block">
        @include('partials.company-logo', [
            'company' => $company,
            'class' => 'doc-brand-logo',
            'width' => 56,
            'height' => 56,
            'forPdf' => $forPdf ?? false,
        ])
        <div class="doc-brand-text">
            <p class="doc-brand-name">{{ $company?->name_ar ?: config('app.name') }}</p>
            @if (filled($company?->address_ar))
                <p class="doc-brand-meta">{{ $company->address_ar }}</p>
            @endif
            @if (filled($company?->contact_phone))
                <p class="doc-brand-meta" dir="ltr">{{ $company->contact_phone }}</p>
            @endif
        </div>
    </div>
    @if ($title !== '')
        <h1 class="doc-brand-title">{{ $title }}</h1>
    @endif
</header>
