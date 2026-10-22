@if ($paginator->hasPages())
    <nav class="ds-pager" role="navigation" aria-label="ترقيم الصفحات">
        <p class="ds-pager-meta">
            @if ($paginator->firstItem())
                عرض
                <strong>{{ $paginator->firstItem() }}</strong>
                –
                <strong>{{ $paginator->lastItem() }}</strong>
                من
                <strong>{{ $paginator->total() }}</strong>
            @else
                لا توجد نتائج
            @endif
        </p>

        <ul class="ds-pager-pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="ds-pager-ellipsis" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="ds-pager-page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="ds-pager-page" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        <div class="ds-pager-nav">
            @if ($paginator->onFirstPage())
                <span class="ds-pager-btn is-disabled" aria-disabled="true">السابق</span>
            @else
                <a class="ds-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="ds-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a>
            @else
                <span class="ds-pager-btn is-disabled" aria-disabled="true">التالي</span>
            @endif
        </div>
    </nav>
@endif
