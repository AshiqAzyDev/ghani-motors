@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Thanks again for contacting Ghani Motors. A member of the team has your enquiry and will follow up with you shortly.</p>
@include('emails.partials.details', ['rows' => [
    'Subject' => $lead->subject,
]])
@endsection
