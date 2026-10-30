<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up dealership | Ghani Motors</title>
    <link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.min.css') }}">
</head>
<body class="setup-body">
<main class="setup-page">
    <aside class="setup-intro">
        <div class="setup-intro-inner">
            <div class="setup-brand">
                <b>GHANI <span>MOTORS</span></b>
                <small>QUALITY USED CARS</small>
            </div>
            <p class="setup-kicker">First-time setup</p>
            <h1>Add your dealership details.</h1>
            <p class="setup-lead">No dealer is configured yet. Enter the showroom details and create an admin login so the site can go live.</p>
            <ul class="setup-points">
                <li>Showroom contact and address</li>
                <li>Website host for this install</li>
                <li>Admin access to the dealer workspace</li>
            </ul>
        </div>
    </aside>

    <section class="setup-panel">
        <form method="post" action="{{ route('setup.dealer.store') }}" class="setup-form">
            @csrf
            <div class="setup-form-head">
                <h2>Dealership setup</h2>
                <p>These details power the public site header, footer and contact options.</p>
            </div>

            @if ($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <div class="setup-block">
                <h3>Showroom</h3>

                <div class="setup-field">
                    <label for="name">Legal / company name</label>
                    <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name', 'Ghani Motors Ltd') }}">
                </div>

                <div class="setup-field">
                    <label for="trading_name">Trading name</label>
                    <input id="trading_name" type="text" name="trading_name" maxlength="160" value="{{ old('trading_name', 'Ghani Motors') }}">
                </div>

                <div class="setup-field">
                    <label for="tagline">Tagline</label>
                    <input id="tagline" type="text" name="tagline" maxlength="160" value="{{ old('tagline', 'QUALITY USED CARS') }}">
                </div>

                <div class="setup-grid">
                    <div class="setup-field">
                        <label for="phone">Phone</label>
                        <input id="phone" type="text" name="phone" maxlength="40" value="{{ old('phone') }}">
                    </div>
                    <div class="setup-field">
                        <label for="email">Public email</label>
                        <input id="email" type="email" name="email" maxlength="255" value="{{ old('email') }}">
                    </div>
                </div>

                <div class="setup-field">
                    <label for="address_line">Address</label>
                    <input id="address_line" type="text" name="address_line" maxlength="255" value="{{ old('address_line', 'Rainham Road South') }}">
                </div>

                <div class="setup-grid">
                    <div class="setup-field">
                        <label for="city">City</label>
                        <input id="city" type="text" name="city" maxlength="120" value="{{ old('city', 'Dagenham') }}">
                    </div>
                    <div class="setup-field">
                        <label for="postcode">Postcode</label>
                        <input id="postcode" type="text" name="postcode" maxlength="16" value="{{ old('postcode', 'RM10 8YT') }}">
                    </div>
                </div>

                <div class="setup-field">
                    <label for="opening_hours">Opening hours</label>
                    <input id="opening_hours" type="text" name="opening_hours" maxlength="255" value="{{ old('opening_hours', 'Mon–Sat 9am–6pm, Sun 10am–4pm') }}">
                </div>

                <div class="setup-field">
                    <label for="website_host">Website host</label>
                    <input id="website_host" type="text" name="website_host" maxlength="255" value="{{ old('website_host', $defaultHost) }}" placeholder="127.0.0.1">
                    <span class="setup-hint">Usually the host in your browser, e.g. 127.0.0.1 or your domain.</span>
                </div>
            </div>

            <div class="setup-block">
                <h3>Admin login</h3>

                <div class="setup-field">
                    <label for="admin_name">Admin name</label>
                    <input id="admin_name" type="text" name="admin_name" required maxlength="160" value="{{ old('admin_name') }}">
                </div>

                <div class="setup-field">
                    <label for="admin_email">Admin email</label>
                    <input id="admin_email" type="email" name="admin_email" required maxlength="255" value="{{ old('admin_email') }}" autocomplete="username">
                </div>

                <div class="setup-grid">
                    <div class="setup-field">
                        <label for="admin_password">Password</label>
                        <input id="admin_password" type="password" name="admin_password" required autocomplete="new-password">
                    </div>
                    <div class="setup-field">
                        <label for="admin_password_confirmation">Confirm password</label>
                        <input id="admin_password_confirmation" type="password" name="admin_password_confirmation" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button class="btn setup-submit" type="submit">Save and continue</button>
        </form>
    </section>
</main>
</body>
</html>
