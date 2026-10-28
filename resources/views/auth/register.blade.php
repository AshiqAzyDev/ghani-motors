<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account | {{ $siteDealer?->displayName() ?? 'Ghani Motors' }}</title>
    <meta name="description" content="Create a {{ $siteDealer?->displayName() ?? 'Ghani Motors' }} account to save cars and manage enquiries.">
    @if ($siteDealer?->faviconUrl())
        <link rel="icon" href="{{ $siteDealer->faviconUrl() }}">
    @endif
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
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
        <h1>Create an account in a couple of minutes.</h1>
        <p>Save cars from the forecourt and keep your details ready when you enquire or book a viewing.</p>
        @include('partials.aa-badge')
        <a class="auth-back" href="{{ route('login') }}">← Back to log in</a>
    </aside>
    <section class="auth-card">
        <form method="post" action="{{ route('register') }}">
            @csrf
            <h2>Create an account</h2>
            <p class="meta">We’ll use these details when you contact the team.</p>
            @if ($errors->any())
                <div class="errors">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <input type="hidden" name="saved_ids" value="">
            <label for="name">Name</label>
            <input id="name" name="name" required autocomplete="name" value="{{ old('name') }}">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" required autocomplete="tel" value="{{ old('phone') }}">
            @include('partials.password-field', ['id' => 'password', 'autocomplete' => 'new-password'])
            <label for="password_confirmation">Confirm password</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <label class="auth-terms">
                <input type="checkbox" name="accepted_terms" value="1" required>
                <span>I accept the <a href="{{ route('privacy') }}">privacy terms</a></span>
            </label>
            <button class="btn" type="submit">Create account</button>
            <p class="auth-foot">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        </form>
    </section>
</main>
<script src="{{ asset('js/site.js') }}"></script>
</body>
</html>
