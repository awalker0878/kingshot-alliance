<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Verifique o seu e-mail Kingshot Alliance',
            'eyebrow' => 'VERIFICAÇÃO DE EMAIL',
            'heading' => 'Verifique seu e-mail',
            'intro' => 'Use o link seguro abaixo para verificar o endereço de e-mail da sua conta Kingshot Alliance.',
            'action' => 'Verifique o endereço de e-mail',
            'expiry' => 'Este link de verificação expira em :minutes minutos.',
            'notice' => 'Kingshot Alliance nunca pedirá sua senha através de uma mensagem de verificação por e-mail.',
        ],
        'email_change_verify' => [
            'subject' => 'Verifique seu novo e-mail Kingshot Alliance',
            'eyebrow' => 'VERIFICAÇÃO DE SEGURANÇA',
            'heading' => 'Verifique seu novo endereço de e-mail',
            'intro' => 'Use o link seguro abaixo para confirmar este novo endereço de e-mail para sua conta Kingshot Alliance. Seu e-mail atual permanece ativo até que a verificação seja bem-sucedida.',
            'action' => 'Verifique o novo endereço de e-mail',
            'expiry' => 'Este link de verificação expira em :minutes minutos.',
            'notice' => 'Se você não solicitou essa alteração, não use esse link e revise a segurança da sua conta.',
        ],
        'email_changed' => [
            'subject' => 'Seu e-mail Kingshot Alliance mudou',
            'eyebrow' => 'AVISO DE SEGURANÇA',
            'heading' => 'O e-mail da sua conta foi alterado',
            'intro' => 'O endereço de e-mail da sua conta Kingshot Alliance foi alterado para :email.',
            'action' => 'Faça login para Kingshot Alliance',
            'notice' => 'Se você não fez essa alteração, proteja sua conta e entre em contato com o administrador do site.',
        ],
        'reset' => [
            'subject' => 'Redefinir sua senha Kingshot Alliance',
            'eyebrow' => 'RECUPERAÇÃO DE CONTA',
            'heading' => 'Redefinir sua senha',
            'intro' => 'Foi solicitada uma redefinição de senha para sua conta Kingshot Alliance. Use o link seguro abaixo se você fez esse pedido.',
            'action' => 'Redefinir senha',
            'expiry' => 'Este link de redefinição de senha expira em :minutes minutos.',
            'notice' => 'Se você não solicitou uma redefinição de senha, pode ignorar esta mensagem. Sua senha não será alterada.',
        ],
        'fallback' => 'Se o botão não funcionar, copie e cole este link no seu navegador:',
        'footer' => 'Kingshot Alliance é um aplicativo independente de gerenciamento de aliança de terceiros para Kingshot jogadores.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Alteração de e-mail solicitada',
            'body' => 'Um novo endereço de e-mail está aguardando verificação. Seu e-mail atual permanece ativo até que o novo endereço seja verificado.',
        ],
        'email_changed' => [
            'title' => 'E-mail da conta alterado',
            'body' => 'O e-mail da sua conta Kingshot Alliance foi alterado após a verificação do novo endereço.',
        ],
        'password_changed' => [
            'title' => 'A senha foi alterada',
            'body' => 'Sua senha Kingshot Alliance foi alterada. Outras sessões e tokens de acesso foram invalidados.',
        ],
        'password_added' => [
            'title' => 'Senha de login adicionada',
            'body' => 'Uma senha Kingshot Alliance foi adicionada como um método de entrada para sua conta.',
        ],
        'password_removed' => [
            'title' => 'Senha de login removida',
            'body' => 'Seu método de login com senha Kingshot Alliance foi removido. O material de redefinição de senha existente não é mais utilizável.',
        ],
        'google_connected' => [
            'title' => 'Google login conectado',
            'body' => 'Uma identidade Google foi conectada como um método de entrada adicional para sua conta Kingshot Alliance.',
        ],
        'google_disconnected' => [
            'title' => 'Google início de sessão desconectado',
            'body' => 'O método de entrada Google foi removido da sua conta Kingshot Alliance.',
        ],
        'passkey_added' => [
            'title' => 'Passkey adicionado',
            'body' => 'Uma nova senha foi registrada como um método de entrada para sua conta Kingshot Alliance.',
        ],
        'passkey_renamed' => [
            'title' => 'Passkey renomeado',
            'body' => 'Um rótulo de senha foi alterado para sua conta Kingshot Alliance.',
        ],
        'passkey_removed' => [
            'title' => 'Passkey removido',
            'body' => 'Um método de início de sessão de chave secreta foi removido da sua conta Kingshot Alliance.',
        ],
        'mfa_enabled' => [
            'title' => 'Autenticação de dois fatores ativada',
            'body' => 'A autenticação de dois fatores baseada em autenticador agora está ativada para sua conta Kingshot Alliance.',
        ],
        'mfa_disabled' => [
            'title' => 'Autenticação de dois fatores desativada',
            'body' => 'A autenticação de dois fatores foi desativada para sua conta Kingshot Alliance.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Códigos de recuperação regenerados',
            'body' => 'Os códigos de recuperação de dois fatores anteriores foram substituídos e não podem mais ser usados.',
        ],
        'deletion_requested' => [
            'title' => 'Solicitação de exclusão de conta',
            'body' => 'Um período de reflexão de sete dias para exclusão começou para sua conta Kingshot Alliance.',
        ],
        'deletion_cancelled' => [
            'title' => 'Exclusão da conta cancelada',
            'body' => 'A solicitação de exclusão pendente para sua conta Kingshot Alliance foi cancelada.',
        ],
    ],
];
