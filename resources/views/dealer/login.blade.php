<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Dealer log in | Ghani Motors</title>
    <link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.min.css') }}">
</head>
<body class="auth-body">
<main class="auth-shell">
    <aside class="auth-aside">
        <p class="wordmark">GHANI <span>MOTORS</span></p>
        <p class="auth-kicker">Dealer workspace</p>
        <h1>Sign in to manage stock, bookings and invoices.</h1>
        <p>Quality used cars, Rainham Road South, Dagenham.</p>
        @include('partials.aa-badge')
        <a class="auth-back" href="{{ route('home') }}">← Back to website</a>
    </aside>
    <section class="auth-card">
        <form method="post" action="{{ route('dealer.login.store') }}">
            @csrf
            <h2>Log in</h2>
            <p class="meta">Use your staff email and password.</p>
            @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required autocomplete="username" value="{{ old('email') }}">
            @include('partials.password-field', ['id' => 'password'])
            <p class="meta" style="margin-top:8px;"><a href="{{ route('dealer.password.request') }}">Forgot password?</a></p>
            <button class="btn" type="submit">Log in</button>
        </form>
    </section>
</main>
<script src="{{ asset('js/site.min.js') }}" defer></script>
</body>
</html>
