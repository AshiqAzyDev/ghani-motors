@php
    $reviewsConfig = config('google_reviews');
    $reviews = collect($reviewsConfig['reviews'] ?? []);
    $rating = $reviewsConfig['rating'] ?? 4.9;
    $count = $reviewsConfig['count'] ?? $reviews->count();
    $label = $reviewsConfig['label'] ?? 'EXCELLENT';
    $eyebrow = $reviewsConfig['eyebrow'] ?? 'Google reviews';
    $title = $reviewsConfig['title'] ?? 'What buyers say about us.';
    $headline = $reviewsConfig['headline'] ?? 'Rated '.$rating.'/5 on Google';
    $mapsUrl = $mapsUrl
        ?? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(
            ($dealer ?? $siteDealer)?->showroomAddress() ?? 'Ghani Motors Dagenham'
        );
@endphp
@if ($reviews->isNotEmpty())
<section class="google-reviews" aria-labelledby="google-reviews-heading">
    <div class="google-reviews-head">
        <div class="eyebrow">{{ $eyebrow }}</div>
        <h2 id="google-reviews-heading">{{ $title }}</h2>
    </div>
    <div class="google-reviews-banner">
        <span class="google-reviews-stars" aria-hidden="true">★★★★★</span>
        <p>{{ $headline }}</p>
    </div>

    <div class="google-reviews-widget" data-google-reviews>
        <aside class="google-reviews-summary">
            <strong>{{ $label }}</strong>
            <div class="google-reviews-stars google-reviews-stars-lg" aria-hidden="true">★★★★★</div>
            <p>Based on {{ number_format($count) }} reviews</p>
            <a class="google-wordmark" href="{{ $mapsUrl }}" target="_blank" rel="noopener" aria-label="View Ghani Motors on Google">
                <span>G</span><span>o</span><span>o</span><span>g</span><span>l</span><span>e</span>
            </a>
        </aside>

        <div class="google-reviews-track-wrap">
            <div class="google-reviews-track" data-reviews-track tabindex="0">
                @foreach ($reviews as $review)
                    @php
                        $quote = (string) ($review['quote'] ?? '');
                        $long = mb_strlen($quote) > 140;
                        $preview = $long ? rtrim(mb_substr($quote, 0, 140)).'…' : $quote;
                        $initial = $review['initial'] ?? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) ($review['name'] ?? 'G'), 0, 1));
                    @endphp
                    <article class="google-review-card" data-review-card>
                        <div class="google-review-top">
                            <span class="google-review-avatar" style="background: {{ $review['color'] ?? '#64748b' }}">{{ $initial }}</span>
                            <div class="google-review-who">
                                <strong>{{ $review['name'] ?? 'Google user' }}</strong>
                                <span>{{ $review['when'] ?? '' }}</span>
                            </div>
                            <span class="google-review-g" aria-hidden="true" title="Google">
                                <svg viewBox="0 0 24 24" width="18" height="18" focusable="false">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="google-review-rating">
                            <span class="google-reviews-stars" aria-label="{{ (int) ($review['stars'] ?? 5) }} out of 5 stars">
                                @for ($i = 0; $i < (int) ($review['stars'] ?? 5); $i++)★@endfor
                            </span>
                            <span class="google-review-verified" title="Google review" aria-label="Google review">
                                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                                    <circle cx="8" cy="8" r="8" fill="#1a73e8"/>
                                    <path d="M6.7 10.6 4.4 8.3l.9-.9 1.4 1.4 3.2-3.2.9.9-4.1 4.1z" fill="#fff"/>
                                </svg>
                            </span>
                        </div>
                        <p class="google-review-quote" data-review-quote data-full="{{ $quote }}" data-preview="{{ $preview }}">{{ $preview }}</p>
                        @if ($long)
                            <button class="google-review-more" type="button" data-review-more>Read more</button>
                        @endif
                    </article>
                @endforeach
            </div>
            <button class="google-reviews-nav google-reviews-prev" type="button" data-reviews-prev aria-label="Previous reviews" hidden>‹</button>
            <button class="google-reviews-nav google-reviews-next" type="button" data-reviews-next aria-label="Next reviews">›</button>
        </div>
    </div>
</section>
@endif
