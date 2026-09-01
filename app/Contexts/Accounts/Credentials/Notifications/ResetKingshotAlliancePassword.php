<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetKingshotAlliancePassword extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $email = $notifiable instanceof CanResetPassword
            ? $notifiable->getEmailForPasswordReset()
            : '';
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject((string) __('accounts.mail.reset.subject'))
            ->view('mail.accounts.security', [
                'eyebrow' => __('accounts.mail.reset.eyebrow'),
                'heading' => __('accounts.mail.reset.heading'),
                'intro' => __('accounts.mail.reset.intro'),
                'actionText' => __('accounts.mail.reset.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.reset.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.reset.notice'),
            ])
            ->text('mail.accounts.security-text', [
                'heading' => __('accounts.mail.reset.heading'),
                'intro' => __('accounts.mail.reset.intro'),
                'actionText' => __('accounts.mail.reset.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.reset.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.reset.notice'),
            ]);
    }
}
