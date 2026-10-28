<?php

namespace App\Notifications;

use App\Mail\BrandedMail;
use Illuminate\Auth\Notifications\ResetPassword;

class CustomerResetPassword extends ResetPassword
{
    public function toMail($notifiable): BrandedMail
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new BrandedMail(
            'Reset your Ghani Motors password',
            'emails.customer.password-reset',
            [
                'heading' => 'Reset your password',
                'eyebrow' => 'Account security',
                'name' => $notifiable->name,
                'dealer' => $notifiable->dealer,
                'actionUrl' => $url,
                'actionLabel' => 'Choose a new password',
            ]
        ))->to($notifiable->email);
    }
}
