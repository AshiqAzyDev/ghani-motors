@php
    $live = $car->show_on_website && $car->website_status !== 'sold' && $car->website_status !== 'hidden';
    $image = $car->images->first();
@endphp
<article class="account-saved-card @unless($live) is-gone @endunless">
    <a class="account-saved-media" href="{{ $live && $car->slug ? route('cars.show', $car->slug) : route('stock') }}">
        @if ($image)
            <img src="{{ $image->sized('w800h600') }}" alt="{{ $car->title() }}" loading="lazy">
        @else
            <div class="account-saved-placeholder">No photo</div>
        @endif
        @unless ($live)
            <span class="account-saved-chip">No longer available</span>
        @endunless
    </a>
    <div class="account-saved-body">
        <b>{{ $car->title() }}</b>
        <p class="meta">
            @if ($live)
                @gbp($car->publicPricePence()) · {{ ucfirst($car->website_status) }}
            @else
                Removed from public stock
            @endif
        </p>
        <div class="account-saved-actions">
            @if ($live && $car->slug)
                <a class="btn smallbtn" href="{{ route('cars.show', $car->slug) }}">View car</a>
            @endif
            <form method="post" action="{{ route('account.unsave', $car) }}">@csrf<button class="btn smallbtn ghost" type="submit">Unsave</button></form>
        </div>
    </div>
</article>
