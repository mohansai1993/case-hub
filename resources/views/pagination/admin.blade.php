@if ($paginator->hasPages())
  <div class="pagination" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <button class="page-btn page-text" disabled>Previous</button>
    @else
      <a class="page-btn page-text" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="page-dots">{{ $element }}</span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <button class="page-btn is-active" aria-current="page">{{ $page }}</button>
          @else
            <a class="page-btn" href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a class="page-btn page-text" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
      <button class="page-btn page-text" disabled>Next</button>
    @endif
  </div>
@endif
