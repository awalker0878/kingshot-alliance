<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Bestätigen Sie Ihre Kingshot Alliance E-Mail',
            'eyebrow' => 'E-Mail Verifizierung',
            'heading' => 'E-Mail bestätigen',
            'intro' => 'Verwenden Sie den untenstehenden sicheren Link, um die E-Mail-Adresse für Ihr Kingshot Alliance-Konto zu bestätigen.',
            'action' => 'E-Mail-Adresse bestätigen',
            'expiry' => 'Dieser Verifizierungslink läuft in :minutes Minuten ab.',
            'notice' => 'Kingshot Alliance wird Sie niemals per E-Mail zur Passwort-Bestätigung fragen.',
        ],
        'email_change_verify' => [
            'subject' => 'Überprüfen Sie Ihre neue Kingshot Alliance E-Mail',
            'eyebrow' => 'SICHERHEITSPÜRFUNG',
            'heading' => 'Bestätigen Sie Ihre neue E-Mail-Adresse',
            'intro' => 'Verwenden Sie den untenstehenden sicheren Link, um diese neue E-Mail-Adresse für Ihr Kingshot Alliance-Konto zu bestätigen. Ihre aktuelle E-Mail-Adresse bleibt aktiv, bis die Verifizierung erfolgreich ist.',
            'action' => 'Neue E-Mail-Adresse bestätigen',
            'expiry' => 'Dieser Verifizierungslink läuft in :minutes Minuten ab.',
            'notice' => 'Wenn Sie diese Änderung nicht angefordert haben, verwenden Sie diesen Link nicht und überprüfen Sie die Sicherheit Ihres Kontos.',
        ],
        'email_changed' => [
            'subject' => 'Ihre Kingshot Alliance E-Mail-Adresse wurde geändert',
            'eyebrow' => 'SICHERHEITSHINWEIS FÜR WEB-SERVER',
            'heading' => 'Die E-Mail-Adresse Ihres Kontos wurde geändert',
            'intro' => 'Die E-Mail-Adresse für Ihr Kingshot Alliance-Konto wurde in :email geändert.',
            'action' => 'Melden Sie sich an Kingshot Alliance',
            'notice' => 'Wenn Sie diese Änderung nicht vorgenommen haben, sichern Sie Ihr Konto und kontaktieren Sie den Administrator der Website.',
        ],
        'reset' => [
            'subject' => 'Setzen Sie Ihr Kingshot Alliance Passwort zurück',
            'eyebrow' => 'Kontowiederherstellung',
            'heading' => 'Setzen Sie Ihr Passwort zurück',
            'intro' => 'Ein Passwort-Reset wurde für Ihr Kingshot Alliance-Konto angefordert. Verwenden Sie den sicheren Link unten, wenn Sie diese Anfrage gestellt haben.',
            'action' => 'Passwort zurücksetzen',
            'expiry' => 'Dieser Link zum Zurücksetzen des Passworts läuft in :minutes Minuten ab.',
            'notice' => 'Wenn Sie kein Zurücksetzen des Passworts angefordert haben, können Sie diese Meldung ignorieren. Ihr Passwort wird sich nicht ändern.',
        ],
        'fallback' => 'Wenn der Button nicht funktioniert, kopieren Sie diesen Link und fügen Sie ihn in Ihren Browser ein:',
        'footer' => 'Kingshot Alliance ist eine unabhängige Allianz-Management-Anwendung für Kingshot Spieler.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'E-Mail-Änderung angefordert',
            'body' => 'Eine neue E-Mail-Adresse wartet auf die Bestätigung. Ihre aktuelle E-Mail-Adresse bleibt aktiv, bis die neue Adresse bestätigt wurde.',
        ],
        'email_changed' => [
            'title' => 'Konto-E-Mail geändert',
            'body' => 'Die E-Mail-Adresse Ihres Kingshot Alliance-Kontos wurde nach der Überprüfung der neuen Adresse geändert.',
        ],
        'password_changed' => [
            'title' => 'Passwort wurde geändert',
            'body' => 'Ihr Kingshot Alliance Passwort wurde geändert. Andere Sitzungen und Zugriffstoken wurden ungültig.',
        ],
        'password_added' => [
            'title' => 'Passwort-Anmeldung hinzugefügt',
            'body' => 'Ein Kingshot Alliance-Passwort wurde als Anmeldemethode für Ihr Konto hinzugefügt.',
        ],
        'password_removed' => [
            'title' => 'Passwort-Anmeldung entfernt',
            'body' => 'Ihre Anmeldemethode für das Passwort Kingshot Alliance wurde entfernt. Bestehendes Material zum Zurücksetzen des Passworts ist nicht mehr nutzbar.',
        ],
        'google_connected' => [
            'title' => 'Google Anmeldung verbunden',
            'body' => 'Eine Identität Google wurde als zusätzliche Anmeldemethode für Ihr Konto Kingshot Alliance verbunden.',
        ],
        'google_disconnected' => [
            'title' => 'Google Anmeldung getrennt',
            'body' => 'Die Anmeldemethode Google wurde aus Ihrem Konto Kingshot Alliance entfernt.',
        ],
        'passkey_added' => [
            'title' => 'Passkey hinzugefügt',
            'body' => 'Ein neuer Passcode wurde als Anmeldemethode für Ihr Kingshot Alliance-Konto registriert.',
        ],
        'passkey_renamed' => [
            'title' => 'Passkey umbenannt',
            'body' => 'Ein Kennwort-Label wurde für Ihr Kingshot Alliance-Konto geändert.',
        ],
        'passkey_removed' => [
            'title' => 'Passkey entfernt',
            'body' => 'Eine Anmeldemethode mit einem Passcode wurde aus Ihrem Kingshot Alliance-Konto entfernt.',
        ],
        'mfa_enabled' => [
            'title' => 'Zwei-Faktor-Authentifizierung aktiviert',
            'body' => 'Die authentifizierungsbasierte Zwei-Faktor-Authentifizierung ist nun für Ihr Kingshot Alliance-Konto aktiviert.',
        ],
        'mfa_disabled' => [
            'title' => 'Zwei-Faktor-Authentifizierung deaktiviert',
            'body' => 'Die Zwei-Faktor-Authentifizierung wurde für Ihr Kingshot Alliance-Konto deaktiviert.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Wiederherstellungscodes regeneriert',
            'body' => 'Ihre bisherigen Zwei-Faktor-Wiederherstellungscodes wurden ersetzt und können nicht mehr verwendet werden.',
        ],
        'deletion_requested' => [
            'title' => 'Kontolöschung angefordert',
            'body' => 'Für Ihr Kingshot Alliance-Konto hat eine siebentägige Kündigungsfrist begonnen.',
        ],
        'deletion_cancelled' => [
            'title' => 'Kontolöschung abgebrochen',
            'body' => 'Die ausstehende Löschungsanfrage für Ihr Kingshot Alliance-Konto wurde storniert.',
        ],
    ],
];
