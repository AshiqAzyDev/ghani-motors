@php($user = auth()->user())
<label>Name</label>
<input name="name" required value="{{ old('name', $user->name ?? '') }}">
<label>Email</label>
<input type="email" name="email" required value="{{ old('email', $user->email ?? '') }}">
<label>Phone</label>
<input name="phone" required value="{{ old('phone', $user->phone ?? '') }}">
<label><input type="checkbox" name="accepted_terms" value="1" required style="width:auto;"> I accept the <a href="{{ route('privacy') }}">privacy terms</a></label>
