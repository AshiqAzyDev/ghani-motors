@php
    $dealer = $dealer ?? \App\Models\Dealer::query()->orderBy('id')->first();
    $appUrl = rtrim((string) config('app.url'), '/');
    $logoUrl = $dealer?->logoUrl() ?: ($appUrl.'/images/ghani-mail-logo.png');
    $place = trim(implode(', ', array_filter([
        $dealer->address_line ?? 'Rainham Road South',
        $dealer->city ?? 'Dagenham',
        $dealer->postcode ?? 'RM10 8YT',
    ])));
    $phone = $dealer->phone ?? null;
@endphp
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Ghani Motors' }}</title>
</head>
<body style="margin:0;padding:0;background:#eef3f6;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $heading ?? ($subject ?? 'Ghani Motors') }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef3f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbe4ea;box-shadow:0 8px 24px rgba(11,25,44,.06);">
                <tr>
                    <td style="background:#0b192c;padding:22px 28px;border-bottom:4px solid #088395;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <a href="{{ $appUrl }}" style="text-decoration:none;">
                                        <img src="{{ $logoUrl }}" alt="Ghani Motors" width="200" height="51" style="display:block;border:0;max-width:200px;height:auto;">
                                    </a>
                                </td>
                                <td align="right" style="color:#9db0ba;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                                    AA Approved Dealer
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        @if (!empty($eyebrow))
                            <div style="display:inline-block;margin:0 0 12px;padding:5px 10px;border-radius:999px;background:#eef8fa;color:#088395;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">{{ $eyebrow }}</div>
                        @endif
                        <h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;letter-spacing:-0.4px;color:#0b192c;">{{ $heading }}</h1>
                        <div style="font-size:15px;line-height:1.65;color:#334155;">
                            @yield('body')
                        </div>
                        @isset($actionUrl)
                            <p style="margin:24px 0 0;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:#088395;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px;">{{ $actionLabel ?? 'Open link' }}</a>
                            </p>
                        @endisset
                        @isset($secondaryUrl)
                            <p style="margin:12px 0 0;">
                                <a href="{{ $secondaryUrl }}" style="color:#088395;font-weight:700;font-size:13px;text-decoration:none;">{{ $secondaryLabel ?? 'Learn more' }} →</a>
                            </p>
                        @endisset
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 28px 24px;background:#f8fafc;border-top:1px solid #e8eef2;color:#64748b;font-size:12px;line-height:1.6;">
                        <strong style="color:#0b192c;">{{ $dealer->trading_name ?? 'Ghani Motors' }}</strong><br>
                        {{ $place }}
                        @if ($phone)<br>Call {{ $phone }}@endif
                        <br><br>
                        This message was sent by Ghani Motors. Finance is subject to status. Terms apply.<br>
                        <a href="{{ $appUrl }}" style="color:#088395;text-decoration:none;">ghanimotors.co.uk</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
