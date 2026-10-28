@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">{{ $reason }}</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Registration' => $vehicle->registration,
]])
<p style="margin:0;">You are welcome to browse the cars currently in stock or speak with the team about a similar car.</p>
@endsection
