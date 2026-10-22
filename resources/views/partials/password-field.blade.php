<label for="{{ $id }}">{{ $label ?? 'Password' }}</label>
<div class="password-field">
    <input id="{{ $id }}" type="password" name="{{ $name ?? 'password' }}" required autocomplete="{{ $autocomplete ?? 'current-password' }}" @if(!empty($min)) minlength="{{ $min }}" @endif>
    <button type="button" class="password-eye" data-password-toggle aria-label="Show password" aria-pressed="false">
        <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="eye-shut" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a18 18 0 0 1-3.2 3.8"/><path d="M6.1 6.8C3.5 8.6 2 12 2 12s3.6 6.5 10 6.5c1.3 0 2.5-.2 3.6-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
    </button>
</div>
