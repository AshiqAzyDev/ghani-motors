<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Used cars in Dagenham') | Ghani Motors</title>
    <meta name="description" content="@yield('description', 'Ghani Motors, used cars on Rainham Road South, Dagenham.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
</head>
<body class="public-site" data-saved="{{ implode(',', auth()->check() ? auth()->user()->savedVehicles()->pluck('vehicle_id')->all() : session('saved_vehicle_ids', [])) }}">
    <header>
        <a class="brand" href="{{ route('home') }}"><b>GHANI<span>MOTORS</span></b><small>QUALITY USED CARS</small></a>
        <nav aria-label="Main navigation" data-menu>
            <a href="{{ route('stock') }}" @class(['active' => request()->routeIs('stock')])>Find a car</a>
            <a href="{{ route('sell') }}" @class(['active' => request()->routeIs('sell')])>Sell your car</a>
            <a href="{{ route('finance') }}" @class(['active' => request()->routeIs('finance')])>Car finance</a>
            <a href="{{ route('about') }}" @class(['active' => request()->routeIs('about')])>About us</a>
            @auth
                <a class="nav-extra" href="{{ route('account') }}">Account</a>
            @else
                <a class="nav-extra" href="{{ route('login') }}">Log in</a>
            @endauth
        </nav>
        @include('partials.aa-badge')
        @if ($call = $siteDealer?->whatsappNumber())
            <a class="call" href="tel:+{{ $call }}">{{ $siteDealer->phone }}</a>
        @endif
        @auth
            <a class="call" href="{{ route('account') }}">Account</a>
        @else
            <a class="call" href="{{ route('login') }}">Log in</a>
        @endauth
        <button class="mobile-menu" type="button" data-menu-button aria-label="Toggle menu">☰</button>
    </header>
    <main>
        @if (session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer>
        <div class="brand"><b>GHANI<span>MOTORS</span></b><small>QUALITY USED CARS</small></div>
        <p>{{ $siteDealer->address_line ?? 'Rainham Road South' }}, {{ $siteDealer->city ?? 'Dagenham' }} {{ $siteDealer->postcode ?? 'RM10 8YT' }}</p>
        <div class="footerlinks">
            <a href="{{ route('stock') }}">Our cars</a>
            <a href="{{ route('sell') }}">Sell your car</a>
            <a href="{{ route('finance') }}">Finance</a>
            <a href="{{ route('about') }}">About us</a>
            <a href="{{ route('privacy') }}">Privacy</a>
            <a href="{{ route('dealer.login') }}">Dealer workspace</a>
        </div>
        <div class="footnote">© {{ $siteDealer->trading_name ?? 'Ghani Motors' }} <span>Finance subject to status. Terms apply.</span></div>
    </footer>
    @include('partials.contact-dock')
    <script src="{{ asset('js/site.js') }}"></script>
</body>
</html>
