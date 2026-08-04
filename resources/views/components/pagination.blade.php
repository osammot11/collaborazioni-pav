@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginazione">
        @if ($paginator->onFirstPage())
            <span class="pagination-link is-disabled">← Precedente</span>
        @else
            <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Precedente</a>
        @endif

        <span class="pagination-info">Pagina {{ $paginator->currentPage() }} di {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Successiva →</a>
        @else
            <span class="pagination-link is-disabled">Successiva →</span>
        @endif
    </nav>
@endif
