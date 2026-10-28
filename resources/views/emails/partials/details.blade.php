@if (!empty($rows))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
    @foreach ($rows as $label => $value)
        @if ($value !== null && $value !== '')
            <tr>
                <td style="padding:10px 14px;background:#f8fafc;border-bottom:1px solid #eef2f6;width:38%;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</td>
                <td style="padding:10px 14px;border-bottom:1px solid #eef2f6;font-size:14px;color:#0f172a;">{{ $value }}</td>
            </tr>
        @endif
    @endforeach
</table>
@endif
