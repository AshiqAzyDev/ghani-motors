@extends('layouts.dealer')
@section('title', 'Staff')
@section('content')
@php($canAssignAdmin = $canAssignAdmin ?? false)
<p class="page-note">Invite staff and control what each person can do in the dealer workspace.</p>

<section class="panel" data-staff-perms data-presets='@json($permissionPresets)'>
    <h2>Invite staff</h2>
    <form method="post" action="{{ route('dealer.staff.store') }}">
        @csrf
        <div class="row">
            <input name="name" placeholder="Name" required value="{{ old('name') }}">
            <input type="email" name="email" placeholder="Email" required value="{{ old('email') }}">
            <select name="role" data-role-preset>
                <option value="sales" @selected(old('role', 'sales') === 'sales')>Sales</option>
                <option value="viewer" @selected(old('role') === 'viewer')>Viewer</option>
                @if ($canAssignAdmin)
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                @endif
            </select>
        </div>
        <div class="perm-grid" data-perm-grid @style(['display: none' => old('role') === 'admin'])>
            @foreach ($permissionLabels as $key => $label)
                <label class="check-inline"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, old('permissions', \App\Support\StaffPermissions::preset(old('role', 'sales'))), true))> {{ $label }}</label>
            @endforeach
        </div>
        <p class="meta" data-admin-note @style(['display: none' => old('role') !== 'admin'])>Admins always have full access. Permission checkboxes are not used.</p>
        <button class="btn" type="submit" style="margin-top:.75rem;">Send invite</button>
    </form>
</section>

@foreach ($rows as $member)
    @php($editableRoles = $canAssignAdmin || $member->role === 'admin' ? ['admin', 'sales', 'viewer'] : ['sales', 'viewer'])
    <section class="panel" style="margin-top:1rem;" data-staff-perms data-presets='@json($permissionPresets)'>
        <h2>{{ $member->name }} <small class="meta">{{ $member->email }} · {{ $member->role }} · {{ $member->is_active ? 'active' : 'inactive' }}</small></h2>
        <form method="post" action="{{ route('dealer.staff.update', $member) }}">
            @csrf
            @method('PUT')
            <div class="row">
                <input name="name" value="{{ old('name', $member->name) }}" required>
                <select name="role" data-role-preset @disabled(! $canAssignAdmin && $member->role === 'admin')>
                    @foreach ($editableRoles as $role)
                        <option value="{{ $role }}" @selected(old('role', $member->role) === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
                @if (! $canAssignAdmin && $member->role === 'admin')
                    <input type="hidden" name="role" value="admin">
                @endif
                <input type="hidden" name="is_active" value="0">
                <label class="check-inline"><input type="checkbox" name="is_active" value="1" @checked((string) old('is_active', $member->is_active ? '1' : '0') === '1')> Active</label>
            </div>
            <div class="perm-grid" data-perm-grid @style(['display: none' => old('role', $member->role) === 'admin'])>
                @foreach ($permissionLabels as $key => $label)
                    <label class="check-inline"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, old('permissions', $member->permissionList()), true))> {{ $label }}</label>
                @endforeach
            </div>
            <p class="meta" data-admin-note @style(['display: none' => old('role', $member->role) !== 'admin'])>Admins always have full access.</p>
            <button class="btn smallbtn" type="submit" style="margin-top:.75rem;">Save</button>
        </form>
        <form method="post" action="{{ route('dealer.staff.invite', $member) }}" style="margin-top:.5rem;">@csrf<button class="rowbtn" type="submit">Resend invite / password link</button></form>
    </section>
@endforeach
@endsection
