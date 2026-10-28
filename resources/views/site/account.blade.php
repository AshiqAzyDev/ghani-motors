@extends('layouts.site')
@section('title', 'Your account')
@section('content')
@php
    $firstName = \Illuminate\Support\Str::of($customer->name)->before(' ')->toString() ?: $customer->name;
    $nav = [
        'overview' => ['label' => 'Overview', 'count' => null],
        'saved' => ['label' => 'Saved cars', 'count' => $counts['saved']],
        'bookings' => ['label' => 'Bookings', 'count' => $counts['bookings']],
        'enquiries' => ['label' => 'Enquiries', 'count' => $counts['enquiries']],
        'invoices' => ['label' => 'Invoices', 'count' => $counts['invoices']],
        'alerts' => ['label' => 'Alerts', 'count' => $counts['alerts'], 'hot' => true],
        'profile' => ['label' => 'Profile', 'count' => null],
    ];
    $titles = [
        'overview' => 'Overview',
        'saved' => 'Saved cars',
        'bookings' => 'Bookings',
        'enquiries' => 'Enquiries',
        'invoices' => 'Invoices & purchases',
        'alerts' => 'Alerts',
        'profile' => 'Profile & security',
    ];
@endphp
<section class="account-shell">
    <aside class="account-sidebar">
        <div class="account-sidebar-head">
            <p class="eyebrow">Your account</p>
            <strong>{{ $customer->name }}</strong>
            <small>{{ $customer->email }}</small>
        </div>
        <nav class="account-side-nav" aria-label="Account navigation">
            @foreach ($nav as $key => $item)
                <a href="{{ $key === 'overview' ? route('account') : route('account', $key) }}" @class(['selected' => $section === $key])>
                    <span>{{ $item['label'] }}</span>
                    @if (! is_null($item['count']))
                        <em @class(['is-hot' => !empty($item['hot']) && $item['count'] > 0])>{{ $item['count'] }}</em>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="account-sidebar-foot">
            <a class="btn smallbtn" href="{{ route('stock') }}">Browse stock</a>
        </div>
    </aside>

    <div class="account-main">
        <header class="account-main-head">
            <div>
                <p class="eyebrow">Ghani Motors</p>
                <h1>{{ $titles[$section] ?? 'Account' }}</h1>
            </div>
            @if ($section === 'overview')
                <p class="meta">Hello, {{ $firstName }} — here’s a snapshot of your activity.</p>
            @elseif ($section === 'saved')
                <p class="meta">{{ $counts['saved'] }} saved car{{ $counts['saved'] === 1 ? '' : 's' }}. Sold or hidden cars stay listed so you can remove them.</p>
            @elseif ($section === 'bookings')
                <p class="meta">{{ $counts['open_bookings'] }} open · {{ $counts['bookings'] }} total</p>
            @elseif ($section === 'alerts')
                <p class="meta">{{ $counts['alerts'] }} unread</p>
            @endif
        </header>

        @include('site.account.'.$section)
    </div>
</section>
@endsection
