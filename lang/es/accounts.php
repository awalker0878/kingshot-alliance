<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Verifica tu correo electrónico Kingshot Alliance',
            'eyebrow' => 'Verificación de correo electrónico',
            'heading' => 'Verifica tu correo electrónico',
            'intro' => 'Utilice el enlace seguro a continuación para verificar la dirección de correo electrónico de su cuenta de Kingshot Alliance.',
            'action' => 'Verificar dirección de correo electrónico',
            'expiry' => 'Este enlace de verificación caduca en :minutes minutos.',
            'notice' => 'Kingshot Alliance nunca le pedirá su contraseña a través de un mensaje de verificación por correo electrónico.',
        ],
        'email_change_verify' => [
            'subject' => 'Verifica tu nuevo correo electrónico Kingshot Alliance',
            'eyebrow' => 'VERIFICACIÓN DE SEGURIDAD',
            'heading' => 'Verifica tu nueva dirección de correo electrónico',
            'intro' => 'Utilice el enlace seguro a continuación para confirmar esta nueva dirección de correo electrónico para su cuenta Kingshot Alliance. Su correo electrónico actual permanece activo hasta que la verificación sea exitosa.',
            'action' => 'Verificar nueva dirección de correo electrónico',
            'expiry' => 'Este enlace de verificación caduca en :minutes minutos.',
            'notice' => 'Si no solicitaste este cambio, no uses este enlace y revisa la seguridad de tu cuenta.',
        ],
        'email_changed' => [
            'subject' => 'Su correo electrónico Kingshot Alliance cambió',
            'eyebrow' => 'AVISO DE SEGURIDAD',
            'heading' => 'El correo electrónico de tu cuenta ha sido cambiado',
            'intro' => 'La dirección de correo electrónico de su cuenta Kingshot Alliance se cambió a :email.',
            'action' => 'Iniciar sesión en Kingshot Alliance',
            'notice' => 'Si no hizo este cambio, asegure su cuenta y póngase en contacto con el administrador del sitio.',
        ],
        'reset' => [
            'subject' => 'Restablecer su contraseña Kingshot Alliance',
            'eyebrow' => 'RECUPERACIÓN DE CUENTA',
            'heading' => 'Restablezca su contraseña',
            'intro' => 'Se ha solicitado un restablecimiento de contraseña para su cuenta Kingshot Alliance. Utilice el enlace seguro a continuación si ha realizado esta solicitud.',
            'action' => 'Restablecer contraseña',
            'expiry' => 'Este enlace de restablecimiento de contraseña caduca en :minutes minutos.',
            'notice' => 'Si no solicitaste un restablecimiento de contraseña, puedes ignorar este mensaje. Su contraseña no cambiará.',
        ],
        'fallback' => 'Si el botón no funciona, copie y pegue este enlace en su navegador:',
        'footer' => 'Kingshot Alliance es una aplicación independiente de terceros para la gestión de alianzas para jugadores Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Cambio de correo electrónico solicitado',
            'body' => 'Una nueva dirección de correo electrónico está esperando verificación. Tu correo electrónico actual permanecerá activo hasta que se verifique la nueva dirección.',
        ],
        'email_changed' => [
            'title' => 'Correo electrónico de la cuenta cambiado',
            'body' => 'El correo electrónico de su cuenta Kingshot Alliance se cambió después de verificar la nueva dirección.',
        ],
        'password_changed' => [
            'title' => 'Contraseña cambiada',
            'body' => 'Su contraseña Kingshot Alliance ha sido cambiada. Otras sesiones y tokens de acceso fueron invalidados.',
        ],
        'password_added' => [
            'title' => 'Se agregó el inicio de sesión con contraseña',
            'body' => 'Se agregó una contraseña Kingshot Alliance como método de inicio de sesión para su cuenta.',
        ],
        'password_removed' => [
            'title' => 'Se ha eliminado el inicio de sesión con contraseña',
            'body' => 'Se ha eliminado el método de inicio de sesión de contraseña Kingshot Alliance. El material existente para restablecer la contraseña ya no se puede utilizar.',
        ],
        'google_connected' => [
            'title' => 'Google inicio de sesión conectado',
            'body' => 'Se conectó una identidad Google como método de inicio de sesión adicional para su cuenta Kingshot Alliance.',
        ],
        'google_disconnected' => [
            'title' => 'Google inicio de sesión desconectado',
            'body' => 'El método de inicio de sesión Google se ha eliminado de su cuenta Kingshot Alliance.',
        ],
        'passkey_added' => [
            'title' => 'Se agregó Passkey',
            'body' => 'Se registró una nueva clave de acceso como método de inicio de sesión para su cuenta Kingshot Alliance.',
        ],
        'passkey_renamed' => [
            'title' => 'Clave de acceso renombrada',
            'body' => 'Se cambió una etiqueta de clave de acceso para su cuenta Kingshot Alliance.',
        ],
        'passkey_removed' => [
            'title' => 'Clave de acceso eliminada',
            'body' => 'Se ha eliminado un método de inicio de sesión con clave de acceso de tu cuenta Kingshot Alliance.',
        ],
        'mfa_enabled' => [
            'title' => 'Autenticación de dos factores habilitada',
            'body' => 'La autenticación de dos factores basada en autenticador ahora está habilitada para tu cuenta Kingshot Alliance.',
        ],
        'mfa_disabled' => [
            'title' => 'Autenticación de dos factores deshabilitada',
            'body' => 'La autenticación de dos factores se ha desactivado para tu cuenta Kingshot Alliance.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Códigos de recuperación regenerados',
            'body' => 'Los códigos de recuperación de dos factores anteriores se reemplazaron y ya no se pueden usar.',
        ],
        'deletion_requested' => [
            'title' => 'Solicitud de eliminación de cuenta',
            'body' => 'Un período de reflexión de siete días comenzó para su cuenta Kingshot Alliance.',
        ],
        'deletion_cancelled' => [
            'title' => 'Eliminación de cuenta cancelada',
            'body' => 'La solicitud de eliminación pendiente para su cuenta Kingshot Alliance se canceló.',
        ],
    ],
];
