<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Zweryfikuj swój adres e-mail Kingshot Alliance',
            'eyebrow' => 'WERYFIKACJA EMAIL',
            'heading' => 'Zweryfikuj e-mail',
            'intro' => 'Skorzystaj z poniższego bezpiecznego łącza, aby zweryfikować adres e-mail swojego konta Kingshot Alliance.',
            'action' => 'Adres e-mail',
            'expiry' => 'Ten link weryfikacyjny wygasa za :minutes minut.',
            'notice' => 'Kingshot Alliance nigdy nie poprosi o podanie hasła w wiadomości e-mail z weryfikacją.',
        ],
        'email_change_verify' => [
            'subject' => 'Zweryfikuj swój nowy adres e-mail Kingshot Alliance',
            'eyebrow' => 'WERYFIKACJA BEZPIECZEŃSTWA',
            'heading' => 'Zweryfikuj swój nowy adres e-mail',
            'intro' => 'Użyj bezpiecznego linku poniżej, aby potwierdzić ten nowy adres e-mail dla swojego konta Kingshot Alliance. Twój obecny adres e-mail pozostaje aktywny do momentu pomyślnej weryfikacji.',
            'action' => 'Zweryfikuj nowy adres e-mail',
            'expiry' => 'Ten link weryfikacyjny wygasa za :minutes minut.',
            'notice' => 'Jeśli nie poprosiłeś o tę zmianę, nie korzystaj z tego linku i sprawdź bezpieczeństwo swojego konta.',
        ],
        'email_changed' => [
            'subject' => 'Twój adres e-mail Kingshot Alliance zmienił się',
            'eyebrow' => 'INFORMACJA DOTYCZĄCA BEZPIECZEŃSTWA',
            'heading' => 'Twój adres e-mail został zmieniony',
            'intro' => 'Adres e-mail dla Twojego konta Kingshot Alliance został zmieniony na :email.',
            'action' => 'Zaloguj się do Kingshot Alliance',
            'notice' => 'Jeśli nie dokonałeś tej zmiany, zabezpiecz swoje konto i skontaktuj się z administratorem witryny.',
        ],
        'reset' => [
            'subject' => 'Zresetuj swoje hasło Kingshot Alliance',
            'eyebrow' => 'Odzyskiwanie konta',
            'heading' => 'Zresetuj swoje hasło',
            'intro' => 'Zgłoszono prośbę o zresetowanie hasła dla Twojego konta Kingshot Alliance. Jeśli złożyłeś tę prośbę, skorzystaj z poniższego bezpiecznego linku.',
            'action' => 'Zresetuj hasło',
            'expiry' => 'Ten link do resetowania hasła wygasa za :minutes minut.',
            'notice' => 'Jeśli nie zgłosiłeś prośby o zresetowanie hasła, możesz zignorować tę wiadomość. Twoje hasło nie ulegnie zmianie.',
        ],
        'fallback' => 'Jeśli przycisk nie działa, skopiuj i wklej ten link do swojej przeglądarki:',
        'footer' => 'Kingshot Alliance jest niezależną aplikacją do zarządzania sojuszami dla graczy Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Zmiana adresu e-mail',
            'body' => 'Nowy adres e-mail czeka na weryfikację. Twój obecny adres e-mail pozostaje aktywny do czasu zweryfikowania nowego adresu.',
        ],
        'email_changed' => [
            'title' => 'Zmieniono adres e-mail konta',
            'body' => 'Twój adres e-mail Kingshot Alliance został zmieniony po weryfikacji nowego adresu.',
        ],
        'password_changed' => [
            'title' => 'Hasło zostało zmienione',
            'body' => 'Twoje hasło Kingshot Alliance zostało zmienione. Inne sesje i tokeny dostępu zostały unieważnione.',
        ],
        'password_added' => [
            'title' => 'Dodano logowanie hasłem',
            'body' => 'Hasło Kingshot Alliance zostało dodane jako metoda logowania dla Twojego konta.',
        ],
        'password_removed' => [
            'title' => 'Usunięto logowanie za pomocą hasła',
            'body' => 'Twoja metoda logowania hasłem Kingshot Alliance została usunięta. Istniejący materiał do resetowania hasła nie jest już użyteczny.',
        ],
        'google_connected' => [
            'title' => 'Google logowanie połączone',
            'body' => 'Tożsamość Google została podłączona jako dodatkowa metoda logowania dla konta Kingshot Alliance.',
        ],
        'google_disconnected' => [
            'title' => 'Google logowanie rozłączone',
            'body' => 'Metoda logowania Google została usunięta z konta Kingshot Alliance.',
        ],
        'passkey_added' => [
            'title' => 'Dodano passkey',
            'body' => 'Nowy klucz dostępu został zarejestrowany jako metoda logowania dla konta Kingshot Alliance.',
        ],
        'passkey_renamed' => [
            'title' => 'Zmieniono nazwę hasła',
            'body' => 'Etykieta klucza dostępu została zmieniona dla konta Kingshot Alliance.',
        ],
        'passkey_removed' => [
            'title' => 'Hasło usunięte',
            'body' => 'Metoda logowania za pomocą klucza została usunięta z konta Kingshot Alliance.',
        ],
        'mfa_enabled' => [
            'title' => 'Uwierzytelnianie dwuskładnikowe włączone',
            'body' => 'Uwierzytelnianie dwuskładnikowe oparte na uwierzytelniaczu jest teraz włączone dla konta Kingshot Alliance.',
        ],
        'mfa_disabled' => [
            'title' => 'Uwierzytelnianie dwuskładnikowe wyłączone',
            'body' => 'Uwierzytelnianie dwuskładnikowe zostało wyłączone dla Twojego konta Kingshot Alliance.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Kody odzyskiwania zregenerowane',
            'body' => 'Poprzednie kody odzyskiwania dwuskładnikowe zostały zastąpione i nie można ich już używać.',
        ],
        'deletion_requested' => [
            'title' => 'Żądanie usunięcia konta',
            'body' => 'Rozpoczął się siedmiodniowy okres karencji dla Twojego konta Kingshot Alliance.',
        ],
        'deletion_cancelled' => [
            'title' => 'Usuwanie konta anulowane',
            'body' => 'Oczekujący wniosek o usunięcie konta Kingshot Alliance został anulowany.',
        ],
    ],
];
