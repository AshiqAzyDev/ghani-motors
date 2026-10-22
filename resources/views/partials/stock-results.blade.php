<p class="results-count">{{ $cars->total() }} {{ \Illuminate\Support\Str::plural('car', $cars->total()) }}</p>
<div class="cards">
    @forelse ($cars as $car)
        @include('partials.car-card', ['car' => $car])
    @empty
        <div class="empty">
            <h3>No cars match that search.</h3>
            <p>Try another make, price or registration.</p>
        </div>
    @endforelse
</div>
@if ($cars->hasPages())
    {{ $cars->links() }}
@endif
