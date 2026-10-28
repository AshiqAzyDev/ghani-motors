<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach (['home' => '1.0', 'stock' => '0.9', 'sell' => '0.7', 'finance' => '0.7', 'about' => '0.6', 'privacy' => '0.3'] as $name => $priority)
        <url>
            <loc>{{ route($name) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>{{ $priority }}</priority>
        </url>
    @endforeach
    @foreach ($cars as $car)
        <url>
            <loc>{{ route('cars.show', $car->slug) }}</loc>
            @if ($car->updated_at)<lastmod>{{ $car->updated_at->toAtomString() }}</lastmod>@endif
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
