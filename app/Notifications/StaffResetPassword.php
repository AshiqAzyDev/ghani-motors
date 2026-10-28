<?php

namespace App\Notifications;

use App\Mail\BrandedMail;
use Illuminate\Auth\Notifications\ResetPassword;

class StaffResetPassword extends ResetPassword
{
    public function toMail($notifiable): BrandedMail
    {
        $url = url(route('dealer.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new BrandedMail(
            'Reset your dealer workspace password',
            'emails.staff.password-reset',
            [
                'heading' => 'Reset your staff password',
                'eyebrow' => 'Dealer workspace',
                'name' => $notifiable->name,
                'dealer' => $notifiable->dealer,
                'actionUrl' => $url,
                'actionLabel' => 'Choose a new password',
            ]
        ))->to($notifiable->email);
    }
}
