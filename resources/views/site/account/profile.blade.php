<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>Contact details</h2>
            <p class="meta">Used for bookings and showroom follow-ups.</p>
        </div>
    </div>
    <form class="account-form" method="post" action="{{ route('account.profile') }}">
        @csrf
        @method('PUT')
        <label>Name<input name="name" value="{{ old('name', $customer->name) }}" required></label>
        <label>Email<input value="{{ $customer->email }}" disabled></label>
        <label>Phone<input name="phone" value="{{ old('phone', $customer->phone) }}" required></label>
        <div class="account-form-row">
            <label>Postcode<input name="postcode" value="{{ old('postcode', $customer->postcode) }}"></label>
            <label>Address<input name="address" value="{{ old('address', $customer->address) }}"></label>
        </div>
        <button class="btn" type="submit">Save profile</button>
    </form>
</section>

<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>Change password</h2>
            <p class="meta">Use at least 8 characters.</p>
        </div>
    </div>
    <form class="account-form" method="post" action="{{ route('account.password') }}">
        @csrf
        @method('PUT')
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>New password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
        <button class="btn ghost" type="submit">Update password</button>
    </form>
</section>
