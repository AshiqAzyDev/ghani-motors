@extends('layouts.site')
@section('title', 'Reset password')
@section('content')
<section class="section"><div class="wrap" style="max-width:480px;">
    <div class="panel">
        <h1>Reset password</h1>
        @if (!empty($sent))
            <p>If that email has an account, a reset link has been sent.</p>
            @if (!empty($debugLink))
                <p>Local link: <a href="{{ $debugLink }}">set a new password</a></p>
            @endif
            <p style="margin-top:1rem;"><a class="textlink" href="{{ route('login') }}">← Back to log in</a></p>
        @else
            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <label>Email</label><input type="email" name="email" required value="{{ old('email') }}">
                <button class="btn" type="submit" style="margin-top:1rem;">Send reset link</button>
            </form>
            <p style="margin-top:1rem;"><a class="textlink" href="{{ route('login') }}">← Back to log in</a></p>
        @endif
    </div>
</div></section>
@endsection
