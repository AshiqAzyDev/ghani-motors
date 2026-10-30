@extends('layouts.dealer')
@section('title', 'Settings')
@section('content')
@php($canEdit = auth('staff')->user()->can('settings.manage'))
<p class="page-note">Website branding and contact details. Integration tools are under Advanced options.</p>
@unless ($canEdit)
    <p class="meta">Your role can view settings but cannot change them.</p>
@endunless

<form method="post" action="{{ route('dealer.settings.update') }}" enctype="multipart/form-data" class="settings-form">
    @csrf
    <div class="settings-grid">
        <section class="panel">
            <h2>Branding</h2>
            <p class="meta">Logo and favicon appear on the public website. Use PNG, JPG, or WebP (ICO also allowed for favicon). SVG is not supported.</p>
            <div class="settings-brand-row">
                <div>
                    <label>Logo</label>
                    @if ($dealer->logoUrl())
                        <div class="settings-preview"><img src="{{ $dealer->logoUrl() }}" alt="Current logo"></div>
                        @if ($canEdit)
                            <label class="check-inline"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                        @endif
                    @endif
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" @disabled(! $canEdit)>
                </div>
                <div>
                    <label>Favicon</label>
                    @if ($dealer->faviconUrl())
                        <div class="settings-preview favicon"><img src="{{ $dealer->faviconUrl() }}" alt="Current favicon"></div>
                        @if ($canEdit)
                            <label class="check-inline"><input type="checkbox" name="remove_favicon" value="1"> Remove current favicon</label>
                        @endif
                    @endif
                    <input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon" @disabled(! $canEdit)>
                </div>
            </div>
            <label>Business name<input name="name" value="{{ old('name', $dealer->name) }}" required @readonly(! $canEdit)></label>
            <label>Trading name<input name="trading_name" value="{{ old('trading_name', $dealer->trading_name) }}" placeholder="Ghani Motors" @readonly(! $canEdit)></label>
            <label>Tagline<input name="tagline" value="{{ old('tagline', $dealer->tagline) }}" placeholder="QUALITY USED CARS" @readonly(! $canEdit)></label>
        </section>

        <section class="panel">
            <h2>Contact &amp; showroom</h2>
            <p class="meta">Phone drives the floating WhatsApp button. Use a UK mobile such as 07907 669002 or +44 7907 669002.</p>
            <p class="meta">WhatsApp button: <strong>{{ $autotrader['whatsapp_live'] ? 'live on the website' : 'hidden until a real number is saved' }}</strong></p>
            <label>Public email<input type="email" name="email" value="{{ old('email', $dealer->email) }}" placeholder="sales@ghanimotors.co.uk" @readonly(! $canEdit)></label>
            <label>Phone<input name="phone" value="{{ old('phone', $dealer->phone) }}" required placeholder="07907 669002" @readonly(! $canEdit)></label>
            <label>Address line<input name="address_line" value="{{ old('address_line', $dealer->address_line) }}" placeholder="Rainham Road South" @readonly(! $canEdit)></label>
            <div class="settings-two">
                <label>City<input name="city" value="{{ old('city', $dealer->city) }}" placeholder="Dagenham" @readonly(! $canEdit)></label>
                <label>Postcode<input name="postcode" value="{{ old('postcode', $dealer->postcode) }}" placeholder="RM10 8YT" @readonly(! $canEdit)></label>
            </div>
            <label>Opening hours<input name="opening_hours" value="{{ old('opening_hours', $dealer->opening_hours) }}" placeholder="Mon–Sat 9am–6pm" @readonly(! $canEdit)></label>
            <label>Website host<input name="website_host" value="{{ old('website_host', $dealer->website_host) }}" placeholder="ghanimotors.co.uk" @readonly(! $canEdit)></label>
        </section>
    </div>

    @if ($canEdit)
        <button class="btn" type="submit" style="margin-top:1rem;">Save website settings</button>
    @endif
</form>

@php($migrationStatus = $migrationStatus ?? [])
@php($migrationState = $migrationStatus['state'] ?? 'error')
@php($pendingMigrations = $migrationStatus['pending'] ?? [])
@php($migrationPillClass = $migrationState === 'up_to_date' ? 'ok' : ($migrationState === 'pending' ? 'warn' : 'bad'))
@php($migrationPillLabel = $migrationState === 'up_to_date' ? 'Up to date' : ($migrationState === 'pending' ? 'Migrations pending' : 'Check failed'))
@php($openAdvanced = $migrationState === 'pending' || session()->has('migration_output') || $errors->has('migrate') || $errors->has('sync'))

<details class="settings-advanced panel" @if ($openAdvanced) open @endif>
    <summary class="settings-advanced-summary">
        <span>
            <strong>Advanced options</strong>
            <small>Autotrader, migrations, webhooks, and sync history</small>
        </span>
        <span class="settings-advanced-chevron" aria-hidden="true">▾</span>
    </summary>

    <div class="settings-advanced-body">
        <section class="settings-advanced-block">
            <h2>Autotrader integration</h2>
            <div class="at-status">
                <div class="at-status-row">
                    <span>Environment</span>
                    <strong class="at-pill">{{ $autotrader['environment'] }}</strong>
                </div>
                <div class="at-status-row">
                    <span>API credentials</span>
                    <strong class="at-pill {{ $autotrader['key_set'] ? 'ok' : 'bad' }}">{{ $autotrader['key_set'] ? 'Set in .env' : 'Missing' }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Webhook secret</span>
                    <strong class="at-pill {{ $autotrader['webhook_set'] ? 'ok' : 'warn' }}">{{ $autotrader['webhook_set'] ? 'Set' : 'Not set — live stock updates blocked' }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Sandbox expiry</span>
                    <strong class="at-pill {{ $autotrader['sandbox_state'] }}">{{ $autotrader['sandbox_label'] }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Advertiser</span>
                    <strong>{{ $autotrader['advertiser_id'] ?: 'not set' }} · on integration: {{ $autotrader['on_integration'] ? 'yes' : 'no' }}</strong>
                </div>
                <div class="at-status-row">
                    <span>API host</span>
                    <strong style="font-weight:500;word-break:break-all;">{{ $autotrader['base_url'] }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Webhook URL</span>
                    <strong style="font-weight:500;word-break:break-all;">{{ url('/webhooks/autotrader') }}</strong>
                </div>
            </div>
            <p style="margin-top:.85rem;">Notifications {{ $dealer->integration->notifications_paused ? 'paused' : 'running' }}. Open API pauses: {{ $blocks }}. Failed webhooks: {{ $failedHooks }}.</p>
            <p>Last sync: {{ $lastSync ? $lastSync->kind.' '.$lastSync->status.' at '.$lastSync->created_at : 'none yet' }}.</p>
            @if ($autotrader['environment'] === 'sandbox' && $autotrader['sandbox_state'] === 'unknown')
                <p class="meta">Set <code>AUTOTRADER_SANDBOX_EXPIRES_AT</code> in <code>.env</code> (YYYY-MM-DD) when Autotrader confirms the end date.</p>
            @endif
            @if (auth('staff')->user()->can('sync.run'))
                <form method="post" action="{{ route('dealer.sync') }}" style="margin-top:.75rem;">@csrf<button class="btn" type="submit">Run Autotrader baseline</button></form>
            @endif
        </section>

        <section id="settings-database" class="settings-advanced-block">
            <h2>Database migrations</h2>
            <p class="meta">Apply pending schema updates without wiping the live database.</p>
            <p class="page-note" style="margin-top:.75rem;">
                This runs <code>php artisan migrate --force</code> for files that have not run yet.
                If a table already exists, migrations add any <strong>missing columns</strong> only — they do not drop,
                truncate, or recreate tables. This is not <code>migrate:fresh</code>.
            </p>

            <div class="at-status" style="margin-top:1rem;">
                <div class="at-status-row">
                    <span>Status</span>
                    <strong class="at-pill {{ $migrationPillClass }}">{{ $migrationPillLabel }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Database</span>
                    <strong>{{ $migrationStatus['database'] ?? config('database.default') }}</strong>
                </div>
                <div class="at-status-row">
                    <span>Applied / files</span>
                    <strong>{{ $migrationStatus['applied_count'] ?? 0 }} / {{ $migrationStatus['expected_count'] ?? 0 }} · Pending: {{ count($pendingMigrations) }}</strong>
                </div>
            </div>

            <p style="margin-top:.85rem;">{{ $migrationStatus['message'] ?? 'Unable to read migration status.' }}</p>

            @if ($pendingMigrations !== [])
                <div class="migration-pending" style="margin-top:.85rem;">
                    <p class="meta" style="margin-bottom:.35rem;">Pending migrations ({{ count($pendingMigrations) }})</p>
                    <ul class="migration-pending-list">
                        @foreach ($pendingMigrations as $migrationName)
                            <li><code>{{ $migrationName }}</code></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canEdit)
                <form method="post" action="{{ route('dealer.settings.migrate') }}" style="margin-top:1rem;" onsubmit="return confirm('Run pending database migrations now? This will not wipe existing data.');">
                    @csrf
                    <button class="btn" type="submit" @disabled($migrationState !== 'pending')>Run pending migrations</button>
                </form>
            @endif

            @if (session('migration_output'))
                <pre class="migration-output">{{ session('migration_output') }}</pre>
            @endif

            @if ($migrationState === 'error')
                <p class="meta" style="margin-top:.85rem;">Fix the database connection before migrating.</p>
            @elseif ($migrationState === 'up_to_date')
                <p class="meta" style="margin-top:.85rem;">No pending migrations.</p>
            @endif
        </section>

        <section class="settings-advanced-block">
            <h2>Sync history</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>When</th><th>Kind</th><th>Status</th><th>Upserted</th><th>Error</th></tr></thead>
                    <tbody>
                        @forelse ($syncRuns as $run)
                            <tr>
                                <td>{{ optional($run->started_at ?? $run->created_at)->format('j M Y H:i') }}</td>
                                <td>{{ $run->kind }}</td>
                                <td><span class="badge {{ $run->status }}">{{ ucfirst($run->status) }}</span></td>
                                <td>{{ $run->vehicles_upserted ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($run->error, 80) ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-row">No sync runs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="settings-advanced-block">
            <h2>Recent webhooks</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Received</th><th>Stock</th><th>Status</th><th>Detail</th></tr></thead>
                    <tbody>
                        @forelse ($webhookEvents as $event)
                            <tr>
                                <td>{{ optional($event->received_at)->format('j M Y H:i') }}</td>
                                <td>{{ $event->stock_id ?: '—' }}</td>
                                <td><span class="badge {{ $event->status }}">{{ ucfirst($event->status) }}</span></td>
                                <td>{{ \Illuminate\Support\Str::limit($event->ignore_reason ?? '', 80) ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row">No webhook events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</details>
@endsection
