<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Verify your Kingshot Alliance email',
            'eyebrow' => 'EMAIL VERIFICATION',
            'heading' => 'Verify your email address',
            'intro' => 'Use the secure link below to verify the email address for your Kingshot Alliance account.',
            'action' => 'Verify email address',
            'expiry' => 'This verification link expires in :minutes minutes.',
            'notice' => 'Kingshot Alliance will never ask for your password through an email verification message.',
        ],
        'reset' => [
            'subject' => 'Reset your Kingshot Alliance password',
            'eyebrow' => 'ACCOUNT RECOVERY',
            'heading' => 'Reset your password',
            'intro' => 'A password reset was requested for your Kingshot Alliance account. Use the secure link below if you made this request.',
            'action' => 'Reset password',
            'expiry' => 'This password reset link expires in :minutes minutes.',
            'notice' => 'If you did not request a password reset, you can ignore this message. Your password will not change.',
        ],
        'fallback' => 'If the button does not work, copy and paste this link into your browser:',
        'footer' => 'Kingshot Alliance is an independent third-party alliance-management application for Kingshot players.',
    ],
];
