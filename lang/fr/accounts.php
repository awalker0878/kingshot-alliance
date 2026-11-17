<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Vérifiez votre adresse email Kingshot Alliance',
            'eyebrow' => 'VÉRIFICATION DE L\'E-MAIL',
            'heading' => 'Vérifiez votre e-mail',
            'intro' => 'Utilisez le lien sécurisé ci-dessous pour vérifier l\'adresse e-mail de votre compte Kingshot Alliance.',
            'action' => 'Vérifier l\'adresse e-mail',
            'expiry' => 'Ce lien de vérification expire dans :minutes minutes.',
            'notice' => 'Kingshot Alliance ne vous demandera jamais votre mot de passe par le biais d\'un message de vérification par courriel.',
        ],
        'email_change_verify' => [
            'subject' => 'Vérifiez votre nouvelle adresse email Kingshot Alliance',
            'eyebrow' => 'VÉRIFICATION DE SÉCURITÉ',
            'heading' => 'Vérifiez votre nouvelle adresse e-mail',
            'intro' => 'Utilisez le lien sécurisé ci-dessous pour confirmer cette nouvelle adresse courriel pour votre compte Kingshot Alliance. Votre adresse e-mail actuelle reste active jusqu\'à ce que la vérification soit réussie.',
            'action' => 'Vérifier la nouvelle adresse e-mail',
            'expiry' => 'Ce lien de vérification expire dans :minutes minutes.',
            'notice' => 'Si vous n\'avez pas demandé cette modification, n\'utilisez pas ce lien et vérifiez la sécurité de votre compte.',
        ],
        'email_changed' => [
            'subject' => 'Votre adresse email Kingshot Alliance a changé',
            'eyebrow' => 'AVIS DE SÉCURITÉ',
            'heading' => 'L\'adresse e-mail de votre compte a été modifiée',
            'intro' => 'L\'adresse e-mail de votre compte Kingshot Alliance a été changée en :email.',
            'action' => 'Connectez-vous au Kingshot Alliance',
            'notice' => 'Si vous n\'avez pas effectué ce changement, sécurisez votre compte et contactez l\'administrateur du site.',
        ],
        'reset' => [
            'subject' => 'Réinitialiser votre mot de passe Kingshot Alliance',
            'eyebrow' => 'RÉCUPÉRATION DE COMPTE',
            'heading' => 'Réinitialiser votre mot de passe',
            'intro' => 'Une réinitialisation du mot de passe a été demandée pour votre compte Kingshot Alliance. Utilisez le lien sécurisé ci-dessous si vous avez fait cette demande.',
            'action' => 'Réinitialiser le mot de passe',
            'expiry' => 'Ce lien de réinitialisation du mot de passe expire dans :minutes minutes.',
            'notice' => 'Si vous n\'avez pas demandé de réinitialisation du mot de passe, vous pouvez ignorer ce message. Votre mot de passe ne changera pas.',
        ],
        'fallback' => 'Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur:',
        'footer' => 'Kingshot Alliance est une application indépendante de gestion d\'alliance pour les joueurs Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Changement d\'adresse e-mail demandé',
            'body' => 'Une nouvelle adresse e-mail est en attente de vérification. Votre adresse e-mail actuelle reste active jusqu\'à ce que la nouvelle adresse soit vérifiée.',
        ],
        'email_changed' => [
            'title' => 'Email du compte changé',
            'body' => 'Votre adresse e-mail de compte Kingshot Alliance a été modifiée après vérification de la nouvelle adresse.',
        ],
        'password_changed' => [
            'title' => 'Mot de passe changé',
            'body' => 'Votre mot de passe Kingshot Alliance a été modifié. Les autres sessions et jetons d\'accès ont été invalidés.',
        ],
        'password_added' => [
            'title' => 'Mot de passe de connexion ajouté',
            'body' => 'Un mot de passe Kingshot Alliance a été ajouté comme méthode de connexion pour votre compte.',
        ],
        'password_removed' => [
            'title' => 'Connexion par mot de passe supprimée',
            'body' => 'Votre méthode de connexion au mot de passe Kingshot Alliance a été supprimée. Le matériel de réinitialisation de mot de passe existant n\'est plus utilisable.',
        ],
        'google_connected' => [
            'title' => 'Google connexion connectée',
            'body' => 'Une identité Google a été connectée comme méthode de connexion supplémentaire pour votre compte Kingshot Alliance.',
        ],
        'google_disconnected' => [
            'title' => 'Google connexion déconnectée',
            'body' => 'La méthode de connexion Google a été supprimée de votre compte Kingshot Alliance.',
        ],
        'passkey_added' => [
            'title' => 'Passkey ajouté',
            'body' => 'Une nouvelle clé de passe a été enregistrée comme méthode de connexion pour votre compte Kingshot Alliance.',
        ],
        'passkey_renamed' => [
            'title' => 'Clé d’accès renommée',
            'body' => 'Une étiquette de mot de passe a été modifiée pour votre compte Kingshot Alliance.',
        ],
        'passkey_removed' => [
            'title' => 'Passkey supprimé',
            'body' => 'Une méthode de connexion par mot de passe a été supprimée de votre compte Kingshot Alliance.',
        ],
        'mfa_enabled' => [
            'title' => 'Authentification à deux facteurs activée',
            'body' => 'L\'authentification à deux facteurs basée sur l\'authentificateur est maintenant activée pour votre compte Kingshot Alliance.',
        ],
        'mfa_disabled' => [
            'title' => 'Authentification à deux facteurs désactivée',
            'body' => 'L\'authentification à deux facteurs a été désactivée pour votre compte Kingshot Alliance.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Codes de récupération régénérés',
            'body' => 'Vos anciens codes de récupération à deux facteurs ont été remplacés et ne peuvent plus être utilisés.',
        ],
        'deletion_requested' => [
            'title' => 'Suppression de compte demandée',
            'body' => 'Une période de réflexion de sept jours a commencé pour votre compte Kingshot Alliance.',
        ],
        'deletion_cancelled' => [
            'title' => 'Suppression de compte annulée',
            'body' => 'La demande de suppression en attente pour votre compte Kingshot Alliance a été annulée.',
        ],
    ],
];
