@if ($paginator->hasPages())
    <nav class="operations-pagination" aria-label="Phân trang">
        @if ($paginator->previousPageUrl())<a class="btn btn-ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">Trang trước</a>@endif
        <span>Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->nextPageUrl())<a class="btn btn-ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">Trang sau</a>@endif
    </nav>
@endif
