<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}</title>
    <meta name="description" content="Log in to your {{ $siteDealer?->displayName() ?? 'Ghani Motors' }} account to save cars and manage enquiries.">
    @if ($siteDealer?->faviconUrl())
        <link rel="icon" href="{{ $siteDealer->faviconUrl() }}">
    @endif
    <link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.min.css') }}">
</head>
<body class="auth-body customer-auth" data-saved="{{ implode(',', session('saved_vehicle_ids', [])) }}">
<main class="auth-shell">
    <aside class="auth-aside">
        <a class="auth-brand" href="{{ route('home') }}">
            @if ($siteDealer?->logoUrl())
                <img class="brand-logo" src="{{ $siteDealer->logoUrl() }}" alt="{{ $siteDealer->displayName() }}">
            @else
                <b>GHANI <span>MOTORS</span></b>
                <small>{{ $siteDealer?->displayTagline() ?? 'Quality used cars' }}</small>
            @endif
        </a>
        <p class="auth-kicker">Customer account</p>
        <h1>Save cars. Pick up where you left off.</h1>
        <p>Log in to keep favourites, follow up on enquiries and come back to the cars that caught your eye.</p>
        <ul class="auth-points">
            <li>Save cars from the forecourt</li>
            <li>Return to your shortlist any time</li>
            <li>AA Approved Dealer in Dagenham</li>
        </ul>
        @include('partials.aa-badge')
        <a class="auth-back" href="{{ route('stock') }}">← Back to stock</a>
    </aside>
    <section class="auth-card">
        <form method="post" action="{{ route('login') }}">
            @csrf
            <h2>Welcome back</h2>
            <p class="meta">Use the email and password for your Ghani Motors account.</p>
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif
            <input type="hidden" name="saved_ids" value="">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required autocomplete="username" value="{{ old('email') }}">
            @include('partials.password-field', ['id' => 'password'])
            <div class="auth-links">
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn" type="submit">Log in</button>
            <p class="auth-foot">New here? <a href="{{ route('register') }}">Create an account</a></p>
        </form>
    </section>
</main>
<script src="{{ asset('js/site.min.js') }}" defer></script>
</body>
</html>
