<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyKingshotAllianceEmail extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject((string) __('accounts.mail.verify.subject'))
            ->view('mail.accounts.security', [
                'eyebrow' => __('accounts.mail.verify.eyebrow'),
                'heading' => __('accounts.mail.verify.heading'),
                'intro' => __('accounts.mail.verify.intro'),
                'actionText' => __('accounts.mail.verify.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.verify.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.verify.notice'),
            ])
            ->text('mail.accounts.security-text', [
                'heading' => __('accounts.mail.verify.heading'),
                'intro' => __('accounts.mail.verify.intro'),
                'actionText' => __('accounts.mail.verify.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.verify.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.verify.notice'),
            ]);
    }
}
