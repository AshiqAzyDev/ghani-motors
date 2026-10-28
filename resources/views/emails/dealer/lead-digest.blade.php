@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">There {{ $count === 1 ? 'is' : 'are' }} <strong>{{ $count }}</strong> open {{ \Illuminate\Support\Str::plural('enquiry', $count) }} waiting in the dealer workspace.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
    @foreach ($leads as $lead)
        <tr>
            <td style="padding:12px 14px;border-bottom:1px solid #eef2f6;">
                <div style="font-weight:700;color:#0b192c;">{{ $lead->customer?->name ?? 'Customer' }}</div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">{{ $lead->subject ?: ucfirst($lead->type) }} · {{ optional($lead->created_at)->format('d M Y H:i') }}</div>
            </td>
        </tr>
    @endforeach
</table>
@endsection
