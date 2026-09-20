@props(['paginator'])

@php
    $window = 2;
    $last = $paginator->lastPage();
    $current = $paginator->currentPage();
@endphp

@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        <ul class="pagination__list">
            {{-- Previous --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pagination__link is-disabled" aria-disabled="true">Previous</span>
                @else
                    <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}">Previous</a>
                @endif
            </li>

            {{-- Page numbers --}}
            @for ($page = 1; $page <= $last; $page++)
                @if ($page === 1 || $page === $last || abs($page - $current) <= $window)
                    <li>
                        @if ($page === $current)
                            <span class="pagination__link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination__link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        @endif
                    </li>
                @elseif (abs($page - $current) === $window + 1)
                    <li><span class="pagination__ellipsis" aria-hidden="true">&hellip;</span></li>
                @endif
            @endfor

            {{-- Next --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}">Next</a>
                @else
                    <span class="pagination__link is-disabled" aria-disabled="true">Next</span>
                @endif
            </li>
        </ul>
        <p class="pagination__summary">{{ $paginator->total() }} photo(s), page {{ $current }} of {{ $last }}</p>
    </nav>
@endif