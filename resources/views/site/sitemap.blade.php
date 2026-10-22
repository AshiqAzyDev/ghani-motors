<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach (['home', 'stock', 'sell', 'finance', 'about', 'privacy'] as $name)
        <url><loc>{{ route($name) }}</loc></url>
    @endforeach
    @foreach ($cars as $car)
        <url><loc>{{ route('cars.show', $car->slug) }}</loc></url>
    @endforeach
</urlset>
