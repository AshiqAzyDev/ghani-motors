<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reset staff password | Ghani Motors</title>
    <link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.min.css') }}">
</head>
<body class="auth-body">
<main class="auth-shell">
    <aside class="auth-aside">
        <p class="wordmark">GHANI <span>MOTORS</span></p>
        <p class="auth-kicker">Dealer workspace</p>
        <h1>Reset your staff password.</h1>
        <p>We will email a secure link if that address is on the workspace.</p>
        <a class="auth-back" href="{{ route('home') }}">← Back to website</a>
    </aside>
    <section class="auth-card">
        @if (!empty($sent))
            <h2>Check your inbox</h2>
            <p class="meta">If that email belongs to a staff account, a reset link is on its way.</p>
            <p><a class="btn" href="{{ route('dealer.login') }}">Back to log in</a></p>
        @else
            <form method="post" action="{{ route('dealer.password.email') }}">
                @csrf
                <h2>Forgot password</h2>
                <p class="meta">Enter the staff email you use to sign in.</p>
                @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required autocomplete="username" value="{{ old('email') }}">
                <button class="btn" type="submit">Email reset link</button>
                <p class="meta" style="margin-top:12px;"><a href="{{ route('dealer.login') }}">Back to log in</a></p>
            </form>
        @endif
    </section>
</main>
</body>
</html>
