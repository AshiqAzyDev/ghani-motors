<div class="stock-countbar">
    <b>{{ number_format($cars->total()) }} {{ \Illuminate\Support\Str::plural('car', $cars->total()) }} in stock</b>
    <span>In stock</span>
</div>
@if ($cars->isEmpty())
    <div class="stock-empty">
        <h3>No cars match that search.</h3>
        <p>Try another make, price or registration.</p>
    </div>
@else
    <div class="stock-grid">
        @foreach ($cars as $car)
            @include('partials.stock-card', ['car' => $car])
        @endforeach
    </div>
@endif
@if ($cars->hasPages())
    <nav class="stock-pager" aria-label="Pagination">
        <p>Showing <b>{{ $cars->firstItem() }}–{{ $cars->lastItem() }}</b> of <b>{{ $cars->total() }}</b> cars in {{ $dealer->city ?? 'Dagenham' }}</p>
        <div>
            @if ($cars->onFirstPage())
                <span class="stock-page-step is-disabled">Prev</span>
            @else
                <a class="stock-page-step" href="{{ $cars->previousPageUrl() }}">Prev</a>
            @endif
            @foreach ($cars->getUrlRange(max(1, $cars->currentPage() - 2), min($cars->lastPage(), $cars->currentPage() + 2)) as $page => $url)
                @if ($page === $cars->currentPage())
                    <span class="is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
            @if ($cars->hasMorePages())
                <a class="stock-page-step" href="{{ $cars->nextPageUrl() }}">Next</a>
            @else
                <span class="stock-page-step is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
