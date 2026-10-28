<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>All saved cars</h2>
            <p class="meta">Page {{ $saved->currentPage() }} of {{ max($saved->lastPage(), 1) }}</p>
        </div>
        <a class="btn smallbtn" href="{{ route('stock') }}">Browse stock</a>
    </div>
    @if ($saved->isEmpty())
        <div class="account-empty">
            <p>You haven’t saved a car yet.</p>
            <a class="btn" href="{{ route('stock') }}">Find cars in stock</a>
        </div>
    @else
        <div class="account-saved-grid">
            @foreach ($saved as $car)
                @include('site.account.partials.saved-card', ['car' => $car])
            @endforeach
        </div>
        <div class="account-pager">{{ $saved->links() }}</div>
    @endif
</section>
