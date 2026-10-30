<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Choose a new password | Ghani Motors</title>
    <link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.min.css') }}">
</head>
<body class="auth-body">
<main class="auth-shell">
    <aside class="auth-aside">
        <p class="wordmark">GHANI <span>MOTORS</span></p>
        <p class="auth-kicker">Dealer workspace</p>
        <h1>Choose a new staff password.</h1>
        <p>Use at least 8 characters. You can sign in as soon as it is saved.</p>
        <a class="auth-back" href="{{ route('dealer.login') }}">← Back to log in</a>
    </aside>
    <section class="auth-card">
        <form method="post" action="{{ route('dealer.password.update') }}">
            @csrf
            <h2>New password</h2>
            @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required autocomplete="username" value="{{ old('email', $email) }}">
            @include('partials.password-field', ['id' => 'password', 'name' => 'password', 'label' => 'New password'])
            @include('partials.password-field', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'label' => 'Confirm password'])
            <button class="btn" type="submit">Save password</button>
        </form>
    </section>
</main>
<script src="{{ asset('js/site.min.js') }}" defer></script>
</body>
</html>
