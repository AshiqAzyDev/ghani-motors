@extends('layouts.site')
@section('title', 'Create an account')
@section('content')
<section class="section"><div class="wrap" style="max-width:480px;">
    <form class="panel" method="post" action="{{ route('register') }}">
        @csrf
        <h1>Create an account</h1>
        <input type="hidden" name="saved_ids" value="">
        <label>Name</label><input name="name" required value="{{ old('name') }}">
        <label>Email</label><input type="email" name="email" required value="{{ old('email') }}">
        <label>Phone</label><input name="phone" required value="{{ old('phone') }}">
        <label>Password</label><input type="password" name="password" required>
        <label>Confirm password</label><input type="password" name="password_confirmation" required>
        <label><input type="checkbox" name="accepted_terms" value="1" required style="width:auto;"> I accept the <a href="{{ route('privacy') }}">privacy terms</a></label>
        <button class="btn" type="submit" style="margin-top:1rem;">Create account</button>
    </form>
</div></section>
@endsection
