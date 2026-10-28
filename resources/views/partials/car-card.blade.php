@php
    $image = $car->images->first();
@endphp
<article class="car home-car">
    <div class="carpic">
        <a href="{{ route('cars.show', $car->slug) }}" aria-label="View {{ $car->title() }}">
            @if ($image)
                <img src="{{ $image->sized('w800h600') }}" alt="{{ $car->title() }}">
            @endif
        </a>
        <span class="tag">{{ ucfirst($car->website_status) }}</span>
        @auth
            <form method="post" action="{{ route('cars.save', $car->slug) }}">
                @csrf
                <button class="heart" type="submit" data-save="{{ $car->id }}" aria-label="Save {{ $car->title() }}">♡</button>
            </form>
        @else
            <a class="heart" href="{{ route('login', ['redirect' => route('cars.show', $car->slug, false)]) }}" aria-label="Log in to save {{ $car->title() }}">♡</a>
        @endauth
    </div>
    <div class="cardbody">
        <a href="{{ route('cars.show', $car->slug) }}"><h3>{{ $car->make }} {{ $car->model }}</h3></a>
        <p>{{ $car->trim ?: $car->attention_grabber }}</p>
        <div class="specs">
            @if ($car->year)<span>{{ $car->year }}</span>@endif
            @if ($car->mileage !== null)<span>{{ number_format((int) $car->mileage) }} miles</span>@endif
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
