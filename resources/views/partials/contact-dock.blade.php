@php($whatsapp = $siteDealer?->whatsappNumber())
<div class="dock">
    @if ($whatsapp)
        <a class="dock-btn whatsapp" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
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
