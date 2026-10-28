@extends('layouts.site')
@section('title', 'Choose a new password')
@section('content')
<section class="section"><div class="wrap" style="max-width:480px;">
    <form class="panel" method="post" action="{{ route('password.update') }}">
        @csrf
        <h1>Choose a new password</h1>
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email</label><input type="email" name="email" required value="{{ old('email', $email) }}">
        <label>New password</label><input type="password" name="password" required>
        <label>Confirm password</label><input type="password" name="password_confirmation" required>
        <button class="btn" type="submit" style="margin-top:1rem;">Update password</button>
    </form>
    <p style="margin-top:1rem;"><a class="textlink" href="{{ route('login') }}">← Back to log in</a></p>
</div></section>
@endsection
