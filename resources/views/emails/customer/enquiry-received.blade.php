@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Thanks for your message. The team at Ghani Motors has your enquiry and will be in touch shortly.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle?->title(),
    'Subject' => $lead->subject,
    'Message' => $lead->message,
]])
<p style="margin:16px 0 8px;font-weight:700;color:#0b192c;">Visit the showroom</p>
<p style="margin:0 0 8px;">{{ $showroomAddress ?? ($dealer->showroomAddress() ?? 'Rainham Road South, Dagenham, RM10 8YT') }}</p>
<p style="margin:0;">
    <a href="{{ $mapUrl ?? ($dealer->mapsSearchUrl() ?? '#') }}" style="color:#088395;font-weight:700;text-decoration:none;">Open in Google Maps</a>
    &nbsp;·&nbsp;
    <a href="{{ $directionsUrl ?? ($dealer->mapsDirectionsUrl() ?? '#') }}" style="color:#088395;font-weight:700;text-decoration:none;">Get directions</a>
</p>
@endsection
