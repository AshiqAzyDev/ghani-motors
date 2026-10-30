@extends('layouts.site')
@section('title', 'Sell or part-exchange')
@section('content')
<section class="flowlayout">
    <div>
        <div class="crumb"><a href="{{ route('home') }}">Home</a> / Sell your car</div>
        <div class="eyebrow">A fresh start, without the fuss</div>
        <h1>Ready to move on?<br>Let’s talk about<br><span style="color:var(--teal)">your car.</span></h1>
        <p class="sub">Sell your car or put its value towards your next one. Start with a few simple details.</p>
        <ol class="flowlist">
            <li><i>1</i><div>Tell us about your car<small>Registration, mileage and a few key details.</small></div></li>
            <li><i>2</i><div>Help us get the full picture<small>Condition, history and optional photos.</small></div></li>
            <li><i>3</i><div>Let’s arrange the next step<small>Our team will review your details and discuss a valuation.</small></div></li>
        </ol>
        <div class="hint">A valuation enquiry is free and comes with no obligation. Any offer is subject to inspection and vehicle checks. Registration is entered by hand.</div>
    </div>
    <div class="panel">
        <div class="steps"><div class="step on"></div><div class="step"></div><div class="step"></div></div>
        <form method="post" action="{{ route('sell.store') }}" enctype="multipart/form-data" data-steps>
            @csrf
            <fieldset class="sell-step active">
                <div class="eyebrow">Step 1 of 3</div>
                <h2>Your car details</h2>
                <p>Let’s start with the essentials.</p>
                <div class="formgrid">
                    <label class="full">Vehicle registration<input class="reg" name="registration" required maxlength="16" value="{{ old('registration') }}" placeholder="AB12 CDE"></label>
                    <label>Current mileage<input type="number" name="mileage" required min="0" value="{{ old('mileage') }}"></label>
                    <label>Make<input name="make" required value="{{ old('make') }}"></label>
                    <label>Model<input name="model" required value="{{ old('model') }}"></label>
                    <label>Year<input type="number" name="year" required value="{{ old('year') }}"></label>
                    <label>Fuel<input name="fuel" required value="{{ old('fuel') }}"></label>
                    <label class="full">Transmission<input name="transmission" required value="{{ old('transmission') }}"></label>
                </div>
                <div class="formactions"><span></span><button class="btn" type="button" data-next>Continue</button></div>
            </fieldset>
            <fieldset class="sell-step">
                <div class="eyebrow">Step 2 of 3</div>
                <h2>A little more about your car</h2>
                <p>These details help the team assess your vehicle.</p>
                <div class="formgrid">
                    <label>I want to
                        <select name="intent">
                            <option value="sell" @selected(old('intent') === 'sell')>Sell</option>
                            <option value="part_exchange" @selected(old('intent') === 'part_exchange')>Part-exchange</option>
                        </select>
                    </label>
                    <label>Condition
                        <select name="condition">@foreach (['excellent','good','fair','needs_attention'] as $option)<option value="{{ $option }}" @selected(old('condition') === $option)>{{ str_replace('_', ' ', $option) }}</option>@endforeach</select>
                    </label>
                    <label>Service history
                        <select name="service_history">@foreach (['full','partial','none','not_sure'] as $option)<option value="{{ $option }}" @selected(old('service_history') === $option)>{{ str_replace('_', ' ', $option) }}</option>@endforeach</select>
                    </label>
                    <label>Outstanding finance
                        <select name="outstanding_finance">@foreach (['no','yes','not_sure'] as $option)<option value="{{ $option }}" @selected(old('outstanding_finance') === $option)>{{ str_replace('_', ' ', $option) }}</option>@endforeach</select>
                    </label>
                    <label>MOT expiry<input type="date" name="mot_expiry" value="{{ old('mot_expiry') }}"></label>
                    <label class="full">Notes<textarea name="notes">{{ old('notes') }}</textarea></label>
                    <label class="full">Photos<input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple></label>
                    <p class="meta">JPG, PNG or WebP only — up to 8 photos.</p>
                </div>
                <div class="formactions"><button class="btn outline" type="button" data-back>Back</button><button class="btn" type="button" data-next>Continue</button></div>
            </fieldset>
            <fieldset class="sell-step">
                <div class="eyebrow">Step 3 of 3</div>
                <h2>How can we reach you?</h2>
                <p>We’ll use these details to discuss your valuation.</p>
                <div class="formgrid">
                    @include('partials.contact-fields')
                    <label>Postcode<input name="postcode" required value="{{ old('postcode') }}"></label>
                    <label>Preferred contact
                        <select name="preferred_contact"><option value="phone">Phone</option><option value="email">Email</option></select>
                    </label>
                </div>
                <div class="formactions"><button class="btn outline" type="button" data-back>Back</button><button class="btn" type="submit">Send details</button></div>
            </fieldset>
        </form>
    </div>
</section>
@endsection
