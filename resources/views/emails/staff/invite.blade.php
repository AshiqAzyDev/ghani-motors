@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">You have been invited to the Ghani Motors dealer workspace as <strong>{{ $role }}</strong>.</p>
<p style="margin:0;">Set your password with the button below, then sign in to manage stock, bookings and invoices.</p>
@endsection
