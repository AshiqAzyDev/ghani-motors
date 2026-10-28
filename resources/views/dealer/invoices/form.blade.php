@extends('layouts.dealer')
@section('title', 'New invoice')
@section('content')
<form class="panel" method="post" action="{{ route('dealer.invoices.store') }}">
    @csrf
    <div class="row">
        <select name="customer_id" required><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
        <select name="sale_id"><option value="">No linked sale</option>@foreach ($sales as $sale)<option value="{{ $sale->id }}">{{ $sale->vehicle_label }} · @gbp($sale->price_pence)</option>@endforeach</select>
        <select name="vat_scheme">
            <option value="margin">VAT margin scheme</option>
            <option value="standard">Standard VAT</option>
            <option value="exempt">Exempt</option>
            <option value="zero_rated">Zero-rated</option>
        </select>
    </div>
    <label><input type="checkbox" name="prices_include_vat" value="1" checked style="width:auto;"> Amounts include VAT when the scheme is standard</label>
    <label>Vehicle label, if there is no sale</label><input name="vehicle_label">
    <h2>Lines</h2>
    <div>
        <div class="row" data-line>
            <select name="lines[0][line_type]">@foreach (['vehicle','extra','part_exchange','fee','discount'] as $type)<option value="{{ $type }}">{{ str_replace('_',' ', $type) }}</option>@endforeach</select>
            <input name="lines[0][description]" placeholder="Description">
            <input name="lines[0][quantity]" type="number" step="0.01" value="1" placeholder="Qty">
            <input name="lines[0][amount]" type="number" step="0.01" placeholder="Amount £">
        </div>
    </div>
    <button class="btn ghost" type="button" data-add-line style="margin-top:.6rem;">Add another line</button>
    <p class="meta">Part-exchange and discount amounts are stored as negatives. Standard VAT uses 20%.</p>
    <button class="btn" type="submit">Create draft</button>
</form>
<script>
document.querySelector('[data-add-line]').addEventListener('click', () => {
    const holder = document.querySelector('[data-line]').parentElement;
    const index = holder.querySelectorAll('[data-line]').length;
    const row = document.querySelector('[data-line]').cloneNode(true);
    row.querySelectorAll('input, select').forEach((field) => {
        field.name = field.name.replace(/lines\[\d+]/, 'lines[' + index + ']');
        if (field.tagName === 'INPUT' && field.type !== 'number') field.value = '';
    });
    holder.appendChild(row);
});
</script>
@endsection
