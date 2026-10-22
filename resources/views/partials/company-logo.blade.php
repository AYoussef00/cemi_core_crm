{{--
  Official Cemi company / platform mark.
  Use on quotations, delivery notes, invoices, and shell chrome.
--}}
@php
    $class = $class ?? 'company-logo';
    $alt = $alt ?? ($company?->name_ar ?? config('app.name'));
    $png = public_path('branding/cemi-logo.png');
    $svg = public_path('branding/cemi-logo.svg');
    // PDF/print: prefer the official PNG mark. UI chrome may still prefer SVG when set.
    $forPdf = (bool) ($forPdf ?? false);
    $preferSvg = (bool) ($preferSvg ?? (! $forPdf));
    if ($preferSvg && file_exists($svg)) {
        $web = asset('branding/cemi-logo.svg');
        $absolute = $svg;
    } elseif (file_exists($png)) {
        $web = asset('branding/cemi-logo.png');
        $absolute = $png;
    } else {
        $web = asset('branding/cemi-logo.svg');
        $absolute = $svg;
    }
@endphp
<img
    class="{{ $class }}"
    src="{{ ($forPdf ?? false) ? $absolute : $web }}"
    alt="{{ $alt }}"
    width="{{ $width ?? 56 }}"
    height="{{ $height ?? 56 }}"
    @unless ($forPdf ?? false) decoding="async" @endunless
>

