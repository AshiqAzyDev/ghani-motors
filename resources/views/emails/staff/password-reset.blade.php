@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Use the button below to choose a new password for the Ghani Motors dealer workspace. This link expires in 60 minutes.</p>
<p style="margin:0;">If you did not ask for a reset, you can ignore this email.</p>
@endsection
