<?php

namespace App\Http\Controllers;

use App\Models\StaffUser;
use App\Services\CustomerDirectory;
use App\Services\Mail\GhaniMailer;
use App\Support\StaffPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct(
        private GhaniMailer $mailer,
        private CustomerDirectory $directory,
    ) {}

    public function index(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('staff.manage'), 403);
        $rows = StaffUser::query()
            ->where('dealer_id', $staff->dealer_id)
            ->orderBy('name')
            ->get();

        return view('dealer.staff.index', [
            'rows' => $rows,
            'permissionLabels' => StaffPermissions::assignableLabels(),
            'permissionPresets' => StaffPermissions::presets(),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user('staff');
        abort_unless($actor->can('staff.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:staff_users,email'],
            'role' => ['required', Rule::in(['admin', 'sales', 'viewer'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(StaffPermissions::assignableLabels()))],
        ]);

        $chosen = array_values(array_diff($data['permissions'] ?? [], StaffPermissions::adminOnly()));
        $permissions = $data['role'] === 'admin'
            ? null
            : ($chosen !== [] ? $chosen : StaffPermissions::preset($data['role']));

        $user = StaffUser::query()->create([
            'dealer_id' => $actor->dealer_id,
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'permissions' => $permissions,
            'is_active' => true,
            'password_hash' => Str::password(32),
        ]);

        $token = Password::broker('staff')->createToken($user);
        $url = url(route('dealer.password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));
        $this->mailer->staffInvite($user->fresh('dealer'), $url);
        $this->directory->log($actor->id, null, null, 'Staff invited: '.$user->email);

        return back()->with('status', 'Invite sent to '.$user->email.'.');
    }

    public function update(Request $request, StaffUser $member)
    {
        $actor = $request->user('staff');
        abort_unless($actor->can('staff.manage'), 403);
        abort_unless((int) $member->dealer_id === (int) $actor->dealer_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'role' => ['required', Rule::in(['admin', 'sales', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(StaffPermissions::assignableLabels()))],
        ]);

        if ((int) $member->id === (int) $actor->id && $data['role'] !== 'admin') {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own admin role.']);
        }

        $member->name = $data['name'];
        $member->role = $data['role'];
        $member->is_active = $request->boolean('is_active', true);
        $chosen = array_values(array_diff($data['permissions'] ?? [], StaffPermissions::adminOnly()));
        $member->permissions = $data['role'] === 'admin'
            ? null
            : ($chosen !== [] ? $chosen : StaffPermissions::preset($data['role']));
        $member->save();
        $this->directory->log($actor->id, null, null, 'Staff updated: '.$member->email);

        return back()->with('status', 'Staff member saved.');
    }

    public function inviteAgain(Request $request, StaffUser $member)
    {
        $actor = $request->user('staff');
        abort_unless($actor->can('staff.manage'), 403);
        abort_unless((int) $member->dealer_id === (int) $actor->dealer_id, 404);

        $token = Password::broker('staff')->createToken($member);
        $url = url(route('dealer.password.reset', [
            'token' => $token,
            'email' => $member->email,
        ], false));
        $this->mailer->staffInvite($member->loadMissing('dealer'), $url);

        return back()->with('status', 'Invite resent to '.$member->email.'.');
    }
}
