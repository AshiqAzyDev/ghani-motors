<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Dealer') | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}</title>
    @if ($siteDealer?->faviconUrl())
        <link rel="icon" href="{{ $siteDealer->faviconUrl() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
</head>
<body class="dealer-mode">
@php
    $nav = [
        'dealer.home' => 'Overview',
        'dealer.vehicles' => 'Vehicles',
        'dealer.bookings' => 'Bookings',
        'dealer.customers' => 'Customers',
        'dealer.leads' => 'Leads',
        'dealer.sales' => 'Sales records',
        'dealer.invoices' => 'Invoices',
        'dealer.staff' => 'Staff',
        'dealer.settings' => 'Settings',
    ];
    $permMap = \App\Support\StaffPermissions::navMap();
@endphp
    <div class="dealer-app">
        <aside class="dealer-sidebar">
            <a class="dealer-brand" href="{{ route('dealer.home') }}">
                @if ($siteDealer?->logoUrl())
                    <img class="dealer-brand-logo" src="{{ $siteDealer->logoUrl() }}" alt="{{ $siteDealer->displayName() }}">
                @else
                    <span>GHANI</span> MOTORS
                @endif
                <small>DEALER WORKSPACE</small>
            </a>
            <div class="workspace-label">MANAGE YOUR DEALERSHIP</div>
            <nav class="dealer-nav" aria-label="Dealer navigation">
                @foreach ($nav as $route => $label)
                    @php($need = $permMap[$route] ?? null)
                    @if (! $need || ($dealerStaff && $dealerStaff->can($need)))
                        <a href="{{ route($route) }}" @class(['selected' => request()->routeIs($route) || request()->routeIs(str_replace('.home', '', $route).'.*') || ($route === 'dealer.leads' && request()->routeIs('dealer.leads.*')) || ($route === 'dealer.customers' && request()->routeIs('dealer.customers.*')) || ($route === 'dealer.staff' && request()->routeIs('dealer.staff.*')) || ($route === 'dealer.invoices' && request()->routeIs('dealer.invoices.*')) || ($route === 'dealer.vehicles' && request()->routeIs('dealer.vehicles.*'))])>{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
            <div class="dealer-sidebottom">
                <div class="feed-status"><i></i> Stock feed</div>
                <p>Autotrader stock stays in sync from here. Manual cars stay off the public site.</p>
                <a href="{{ route('home') }}">View the website ↗</a>
            </div>
        </aside>
        <div class="dealer-main">
            <div class="dealer-top">
                <div>
                    <small>{{ $siteDealer?->displayName() ?? 'GHANI MOTORS' }}@if($dealerStaff) · {{ $dealerStaff->name }} ({{ $dealerStaff->role }})@endif</small>
                    <h1>@yield('title', 'Dealer')</h1>
                </div>
                <div class="dealer-topactions">
                    <a class="dealer-view-site btn smallbtn ghost" href="{{ route('home') }}">View website</a>
                    @include('partials.dealer-notifications')
                    @yield('actions')
                    <form method="post" action="{{ route('dealer.logout') }}">@csrf<button class="btn smallbtn" type="submit">Log out</button></form>
                </div>
            </div>
            <div class="dealer-content">
                @if (session('status'))<p class="flash">{{ session('status') }}</p>@endif
                @if ($errors->any())
                    <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
    <script src="{{ asset('js/dealer.js') }}" defer></script>
</body>
</html>
