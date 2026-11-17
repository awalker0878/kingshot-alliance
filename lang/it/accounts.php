<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Verifica la tua email Kingshot Alliance',
            'eyebrow' => 'VERIFICA DELLA EMAIL',
            'heading' => 'Verifica la tua email',
            'intro' => 'Utilizza il link sicuro qui sotto per verificare l\'indirizzo email del tuo account Kingshot Alliance.',
            'action' => 'Verifica indirizzo email',
            'expiry' => 'Questo link di verifica scade tra :minutes minuti.',
            'notice' => 'Kingshot Alliance non ti chiederà mai la password tramite un messaggio di verifica via email.',
        ],
        'email_change_verify' => [
            'subject' => 'Verifica la tua nuova email Kingshot Alliance',
            'eyebrow' => 'VERIFICA DI SICUREZZA',
            'heading' => 'Verifica il tuo nuovo indirizzo email',
            'intro' => 'Utilizza il link sicuro qui sotto per confermare questo nuovo indirizzo email per il tuo account Kingshot Alliance. La tua email attuale rimane attiva fino al completamento della verifica.',
            'action' => 'Verifica nuovo indirizzo email',
            'expiry' => 'Questo link di verifica scade tra :minutes minuti.',
            'notice' => 'Se non hai richiesto questa modifica, non utilizzare questo link e controlla la sicurezza del tuo account.',
        ],
        'email_changed' => [
            'subject' => 'La tua email Kingshot Alliance è cambiata',
            'eyebrow' => 'AVVISO DI SICUREZZA',
            'heading' => 'L\'email del tuo account è stata modificata',
            'intro' => 'L\'indirizzo email del tuo account Kingshot Alliance è stato modificato in :email.',
            'action' => 'Accedi al Kingshot Alliance',
            'notice' => 'Se non hai effettuato questa modifica, proteggi il tuo account e contatta l\'amministratore del sito.',
        ],
        'reset' => [
            'subject' => 'Resetta la tua password Kingshot Alliance',
            'eyebrow' => 'RECUPERO DELL\'ACCOUNT',
            'heading' => 'Resetta la tua password',
            'intro' => 'È stato richiesto un reset della password per il tuo account Kingshot Alliance. Utilizza il link sicuro qui sotto se hai effettuato questa richiesta.',
            'action' => 'Reimposta password',
            'expiry' => 'Questo link per reimpostare la password scade tra :minutes minuti.',
            'notice' => 'Se non hai richiesto la reimpostazione della password, puoi ignorare questo messaggio. La tua password non cambierà.',
        ],
        'fallback' => 'Se il pulsante non funziona, copia e incolla questo link nel tuo browser:',
        'footer' => 'Kingshot Alliance è un\'applicazione indipendente di gestione delle alleanze per i giocatori Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Email change richiesto',
            'body' => 'Un nuovo indirizzo email è in attesa di verifica. L\'indirizzo email attuale rimane attivo fino alla verifica del nuovo indirizzo.',
        ],
        'email_changed' => [
            'title' => 'L\'email dell\'account è cambiata',
            'body' => 'L\'indirizzo email del tuo account Kingshot Alliance è stato modificato dopo la verifica del nuovo indirizzo.',
        ],
        'password_changed' => [
            'title' => 'La password è cambiata',
            'body' => 'La tua password Kingshot Alliance è stata modificata. Altre sessioni e token di accesso sono stati invalidati.',
        ],
        'password_added' => [
            'title' => 'Password di accesso aggiunto',
            'body' => 'Una password Kingshot Alliance è stata aggiunta come metodo di accesso per il tuo account.',
        ],
        'password_removed' => [
            'title' => 'Password di accesso rimossa',
            'body' => 'Il metodo di accesso con password Kingshot Alliance è stato rimosso. Il materiale esistente per il ripristino della password non è più utilizzabile.',
        ],
        'google_connected' => [
            'title' => 'Google accesso connesso',
            'body' => 'Un\'identità Google è stata collegata come metodo di accesso aggiuntivo per l\'account Kingshot Alliance.',
        ],
        'google_disconnected' => [
            'title' => 'Google accesso disconnesso',
            'body' => 'Il metodo di accesso Google è stato rimosso dall\'account Kingshot Alliance.',
        ],
        'passkey_added' => [
            'title' => 'Passkey aggiunto',
            'body' => 'Una nuova passkey è stata registrata come metodo di accesso per l\'account Kingshot Alliance.',
        ],
        'passkey_renamed' => [
            'title' => 'Passkey rinominato',
            'body' => 'Un\'etichetta passkey è stata modificata per il tuo account Kingshot Alliance.',
        ],
        'passkey_removed' => [
            'title' => 'Passkey rimosso',
            'body' => 'Un metodo di accesso passkey è stato rimosso dal tuo account Kingshot Alliance.',
        ],
        'mfa_enabled' => [
            'title' => 'Autenticazione a due fattori abilitata',
            'body' => 'L\'autenticazione a due fattori basata su autenticatore è ora abilitata per il tuo account Kingshot Alliance.',
        ],
        'mfa_disabled' => [
            'title' => 'Autenticazione a due fattori disabilitata',
            'body' => 'L\'autenticazione a due fattori è stata disabilitata per il tuo account Kingshot Alliance.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Codici di recupero rigenerati',
            'body' => 'I precedenti codici di recupero a due fattori sono stati sostituiti e non possono più essere utilizzati.',
        ],
        'deletion_requested' => [
            'title' => 'Cancellazione dell\'account richiesta',
            'body' => 'È iniziato un periodo di riflessione di sette giorni per l\'eliminazione del tuo account Kingshot Alliance.',
        ],
        'deletion_cancelled' => [
            'title' => 'Cancellazione account annullata',
            'body' => 'La richiesta di eliminazione in sospeso per il tuo account Kingshot Alliance è stata annullata.',
        ],
    ],
];
