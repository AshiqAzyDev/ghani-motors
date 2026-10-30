@extends('layouts.dealer')
@section('title', 'Policies')
@section('content')
<p class="page-note">Upload compliance policy PDFs. They appear on the About page for customers to download.</p>
@unless ($canManage)
    <p class="meta">Your role can view policies but cannot change them.</p>
@endunless

@if ($canManage)
    <section class="panel">
        <h2>Add compliance policy</h2>
        <form method="post" action="{{ route('dealer.policies.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <label style="flex:1.4;">Policy name
                    <input name="name" value="{{ old('name') }}" required maxlength="160" placeholder="e.g. Privacy Policy">
                </label>
                <label style="flex:1;">PDF file
                    <input type="file" name="pdf" accept=".pdf,application/pdf" required>
                </label>
            </div>
            <p class="meta">PDF only, up to 10&nbsp;MB.</p>
            <button class="btn" type="submit" style="margin-top:.75rem;">Upload policy</button>
        </form>
    </section>
@endif

<section class="panel" style="margin-top:1rem;">
    <h2>Published policies</h2>
    @if ($policies->isEmpty())
        <p class="meta">No compliance policies uploaded yet.</p>
    @else
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>File</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($policies as $policy)
                        <tr>
                            <td><strong>{{ $policy->name }}</strong></td>
                            <td class="meta">{{ $policy->original_filename }}</td>
                            <td class="row-actions">
                                <a class="rowbtn" href="{{ route('compliance-policies.download', $policy) }}">Download</a>
                                @if ($canManage)
                                    <form method="post" action="{{ route('dealer.policies.destroy', $policy) }}" onsubmit="return confirm('Remove this policy?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rowbtn" type="submit">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
