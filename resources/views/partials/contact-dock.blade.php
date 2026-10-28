@php($whatsapp = $siteDealer?->whatsappNumber())
<div class="dock" aria-label="Quick contact">
    @if ($whatsapp)
        <a class="wa-fab"
           href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hi Ghani Motors, I have a question about a car.') }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Chat on WhatsApp">
            <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M19.11 17.43c-.3-.15-1.77-.87-2.05-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.48-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.9 1.23 3.1c.15.2 2.12 3.24 5.14 4.54.72.31 1.28.5 1.72.64.72.23 1.38.2 1.9.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35z"/>
                <path fill="currentColor" d="M16.02 3C9.39 3 4 8.38 4 15c0 2.12.55 4.19 1.6 6.02L4 29l8.16-2.14A11.94 11.94 0 0 0 16.02 27C22.66 27 28 21.62 28 15S22.66 3 16.02 3zm0 21.82c-1.9 0-3.76-.51-5.38-1.47l-.39-.23-4.84 1.27 1.29-4.72-.25-.4A9.8 9.8 0 0 1 6.2 15c0-5.42 4.41-9.82 9.82-9.82S25.84 9.58 25.84 15s-4.41 9.82-9.82 9.82z"/>
            </svg>
            <span>WhatsApp</span>
        </a>
    @endif
    <button class="dock-btn" type="button" data-enquiry-open>Enquire</button>
</div>
<dialog class="enquiry" id="enquiry">
    <form method="post" action="{{ route('enquiry.store') }}">
        @csrf
        <h2>Enquire</h2>
        @include('partials.contact-fields')
        <label>Message</label>
        <textarea name="message">{{ old('message') }}</textarea>
        <div class="row" style="margin-top:.8rem;">
            <button class="btn" type="submit">Send</button>
            <button class="btn ghost" type="button" data-enquiry-close>Close</button>
        </div>
    </form>
</dialog>
