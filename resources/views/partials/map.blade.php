@php
    $place = trim(($siteDealer->address_line ?? 'Rainham Road South').', '.($siteDealer->city ?? 'Dagenham').', '.($siteDealer->postcode ?? 'RM10 8YT'));
    $query = rawurlencode($place);
@endphp
<section class="map">
    <iframe title="Ghani Motors on Google Maps" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q={{ $query }}&z=15&output=embed"></iframe>
    <div class="body">
        <p style="margin:0;">{{ $place }}</p>
        <a class="btn" href="https://www.google.com/maps/dir/?api=1&destination={{ $query }}" target="_blank" rel="noopener">Get directions</a>
    </div>
</section>
