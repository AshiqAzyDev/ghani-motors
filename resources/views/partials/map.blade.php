@php
    $dealer = $siteDealer ?? \App\Models\Dealer::current();
    $place = $dealer->showroomAddress();
    $query = rawurlencode($place);
@endphp
<section class="map">
    <iframe title="Ghani Motors on Google Maps" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="{{ $dealer->mapsEmbedUrl() }}"></iframe>
    <div class="body">
        <p style="margin:0;">{{ $place }}</p>
        <a class="btn" href="{{ $dealer->mapsDirectionsUrl() }}" target="_blank" rel="noopener">Get directions</a>
    </div>
</section>
