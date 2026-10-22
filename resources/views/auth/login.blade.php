@extends('layouts.site')
@section('title', 'Log in')
@section('content')
<section class="section"><div class="wrap" style="max-width:480px;">
    <form class="panel" method="post" action="{{ route('login') }}">
        @csrf
        <h1>Customer log in</h1>
        <input type="hidden" name="saved_ids" value="">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required autocomplete="username" value="{{ old('email') }}">
        @include('partials.password-field', ['id' => 'password'])
        <button class="btn" type="submit" style="margin-top:1rem;">Log in</button>
        <p><a href="{{ route('register') }}">Create an account</a> · <a href="{{ route('password.request') }}">Forgot password</a></p>
    </form>
</div></section>
@endsection
