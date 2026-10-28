<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Models\StaffUser;
use App\Services\Mail\GhaniMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteStaffUser extends Command
{
    protected $signature = 'staff:invite
        {email : Staff email address}
        {name : Display name}
        {--role=sales : admin, sales or viewer}
        {--dealer=1 : Dealer id}';

    protected $description = 'Create a staff user (if needed) and email a branded invite to set a password';

    public function handle(GhaniMailer $mailer): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));
        $role = (string) $this->option('role');
        $dealerId = (int) $this->option('dealer');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid email.');

            return self::FAILURE;
        }

        if (! in_array($role, ['admin', 'sales', 'viewer'], true)) {
            $this->error('Role must be admin, sales or viewer.');

            return self::FAILURE;
        }

        $dealer = Dealer::query()->findOrFail($dealerId);
        $staff = StaffUser::query()->where('email', $email)->first();

        if (! $staff) {
            $staff = StaffUser::query()->create([
                'dealer_id' => $dealer->id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'is_active' => true,
                'password_hash' => Str::password(32),
            ]);
        } else {
            $staff->name = $name;
            $staff->role = $role;
            $staff->dealer_id = $dealer->id;
            $staff->is_active = true;
            $staff->save();
        }

        $token = Password::broker('staff')->createToken($staff);
        $url = url(route('dealer.password.reset', [
            'token' => $token,
            'email' => $staff->email,
        ], false));

        $mailer->staffInvite($staff->fresh('dealer'), $url);
        $this->info("Invite sent to {$staff->email}.");

        return self::SUCCESS;
    }
}
