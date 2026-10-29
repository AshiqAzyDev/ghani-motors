<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Used cars in Dagenham') | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}</title>
    <meta name="description" content="@yield('description', ($siteDealer?->displayName() ?? 'Ghani Motors').', used cars on '.($siteDealer?->showroomAddress() ?? 'Rainham Road South, Dagenham').'.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Used cars') | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}">
    <meta property="og:description" content="@yield('description', ($siteDealer?->displayName() ?? 'Ghani Motors').', used cars on '.($siteDealer?->showroomAddress() ?? 'Rainham Road South, Dagenham').'.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', $siteDealer?->logoUrl() ?: asset('images/ghani-mail-logo.png'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Used cars') | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}">
    <meta name="twitter:description" content="@yield('description', ($siteDealer?->displayName() ?? 'Ghani Motors').', used cars on '.($siteDealer?->showroomAddress() ?? 'Rainham Road South, Dagenham').'.')">
    <meta name="twitter:image" content="@yield('og_image', $siteDealer?->logoUrl() ?: asset('images/ghani-mail-logo.png'))">
    @if ($siteDealer?->faviconUrl())
        <link rel="icon" href="{{ $siteDealer->faviconUrl() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
</head>
<body class="public-site" data-saved="{{ implode(',', auth()->check() ? auth()->user()->savedVehicles()->pluck('vehicle_id')->all() : session('saved_vehicle_ids', [])) }}">
    <header>
        <a class="brand" href="{{ route('home') }}">
            @if ($siteDealer?->logoUrl())
                <img class="brand-logo" src="{{ $siteDealer->logoUrl() }}" alt="{{ $siteDealer->displayName() }}">
            @else
                <b>GHANI<span>MOTORS</span></b><small>{{ $siteDealer?->displayTagline() ?? 'QUALITY USED CARS' }}</small>
            @endif
        </a>
        <nav id="main-nav" aria-label="Main navigation" data-menu>
            <a href="{{ route('stock') }}" @class(['active' => request()->routeIs('stock')])>Find a car</a>
            <a href="{{ route('sell') }}" @class(['active' => request()->routeIs('sell')])>Sell your car</a>
            <a href="{{ route('finance') }}" @class(['active' => request()->routeIs('finance')])>Car finance</a>
            <a href="{{ route('about') }}" @class(['active' => request()->routeIs('about')])>About us</a>
            @auth
                <a href="{{ route('account') }}" @class(['nav-account', 'active' => request()->routeIs('account') || request()->routeIs('account.*')])>My account</a>
                <form class="nav-logout" method="post" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
            @else
                {{-- <a href="{{ route('register') }}">Create account</a> --}}
                <a class="nav-login" href="{{ route('login') }}">Log in</a>
            @endauth
        </nav>
        @include('partials.aa-badge')
        @if ($call = $siteDealer?->whatsappNumber())
            <a class="call call-phone" href="tel:+{{ $call }}">{{ $siteDealer->phone }}</a>
        @elseif ($siteDealer?->publicEmail())
            <a class="call call-phone" href="mailto:{{ $siteDealer->publicEmail() }}">{{ $siteDealer->publicEmail() }}</a>
        @endif
        @auth
            <a class="call call-account" href="{{ route('account') }}">Dashboard</a>
            <form class="call-logout" method="post" action="{{ route('logout') }}">@csrf<button class="call call-logout-btn" type="submit">Log out</button></form>
        @else
            <a class="call call-login" href="{{ route('login') }}">Log in</a>
        @endauth
        <button class="mobile-menu" type="button" data-menu-button aria-label="Toggle menu" aria-expanded="false" aria-controls="main-nav">☰</button>
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
        <div class="brand">
            @if ($siteDealer?->logoUrl())
                <img class="brand-logo brand-logo-footer" src="{{ $siteDealer->logoUrl() }}" alt="{{ $siteDealer->displayName() }}">
            @else
                <b>GHANI<span>MOTORS</span></b><small>{{ $siteDealer?->displayTagline() ?? 'QUALITY USED CARS' }}</small>
            @endif
        </div>
        <p>{{ $siteDealer?->showroomAddress() ?? 'Rainham Road South, Dagenham RM10 8YT' }}</p>
        @if ($siteDealer?->opening_hours)<p>{{ $siteDealer->opening_hours }}</p>@endif
        @if ($siteDealer?->publicEmail())<p><a href="mailto:{{ $siteDealer->publicEmail() }}">{{ $siteDealer->publicEmail() }}</a></p>@endif
        <div class="footerlinks">
            <a href="{{ route('stock') }}">Our cars</a>
            <a href="{{ route('sell') }}">Sell your car</a>
            <a href="{{ route('finance') }}">Finance</a>
            <a href="{{ route('about') }}">About us</a>
            <a href="{{ route('privacy') }}">Privacy</a>
            <a href="{{ route('dealer.login') }}">Dealer workspace</a>
        </div>
        <div class="footnote">© {{ $siteDealer?->displayName() ?? 'Ghani Motors' }} <span>Finance subject to status. Terms apply.</span></div>
    </footer>
    @include('partials.contact-dock')
    <script src="{{ asset('js/site.js') }}"></script>
</body>
</html>
