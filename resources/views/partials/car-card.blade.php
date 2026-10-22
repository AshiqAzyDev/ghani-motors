<article class="car">
    <div class="carpic">
        <a href="{{ route('cars.show', $car->slug) }}" aria-label="View {{ $car->title() }}">
            @if ($image = $car->images->first())
                <img src="{{ $image->url }}" alt="{{ $car->title() }}">
            @endif
        </a>
        <span class="tag">{{ ucfirst($car->website_status) }}</span>
        <form method="post" action="{{ route('cars.save', $car->slug) }}">
            @csrf
            <button class="heart" type="submit" data-save="{{ $car->id }}" aria-label="Save {{ $car->title() }}">♡</button>
        </form>
    </div>
    <div class="cardbody">
        <a href="{{ route('cars.show', $car->slug) }}"><h3>{{ $car->make }} {{ $car->model }}</h3></a>
        <p>{{ $car->trim ?: $car->attention_grabber }}</p>
        <div class="specs">
            @if ($car->year)<span>{{ $car->year }}</span>@endif
            <span>{{ number_format((int) $car->mileage) }} miles</span>
            @if ($car->transmission)<span>{{ $car->transmission }}</span>@endif
            @if ($car->fuel)<span>{{ $car->fuel }}</span>@endif
        </div>
        <div class="price">
            <div>
                <small class="cashlabel">Cash price</small>
                <strong>@gbp($car->publicPricePence())</strong>
            </div>
            <a href="{{ route('cars.show', $car->slug) }}">View car ↗</a>
        </div>
    </div>
</article>
