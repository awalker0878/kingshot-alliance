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
        'email_change_verify' => [
            'subject' => 'Verify your new Kingshot Alliance email',
            'eyebrow' => 'SECURITY VERIFICATION',
            'heading' => 'Verify your new email address',
            'intro' => 'Use the secure link below to confirm this new email address for your Kingshot Alliance account. Your current email remains active until verification succeeds.',
            'action' => 'Verify new email address',
            'expiry' => 'This verification link expires in :minutes minutes.',
            'notice' => 'If you did not request this change, do not use this link and review your account security.',
        ],
        'email_changed' => [
            'subject' => 'Your Kingshot Alliance email changed',
            'eyebrow' => 'SECURITY NOTICE',
            'heading' => 'Your account email was changed',
            'intro' => 'The email address for your Kingshot Alliance account was changed to :email.',
            'action' => 'Sign in to Kingshot Alliance',
            'notice' => 'If you did not make this change, secure your account and contact the site administrator.',
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
    'security' => [
        'email_change_requested' => [
            'title' => 'Email change requested',
            'body' => 'A new email address is waiting for verification. Your current email remains active until the new address is verified.',
        ],
        'email_changed' => [
            'title' => 'Account email changed',
            'body' => 'Your Kingshot Alliance account email was changed after verification of the new address.',
        ],
        'password_changed' => [
            'title' => 'Password changed',
            'body' => 'Your Kingshot Alliance password was changed. Other sessions and access tokens were invalidated.',
        ],
    ],
];
