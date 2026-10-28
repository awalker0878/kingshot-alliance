import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Iniciar sesión',
      email: 'Correo electrónico',
      password: 'Contraseña',
      remember: 'Recordarme',
      forgotPassword: '¿Olvidaste la contraseña?',
      submit: 'Iniciar sesión',
      createAccount: 'Crear cuenta',
      invitation: '¿Tienes una invitación?',
    },
    register: {
      title: 'Crear cuenta',
      name: 'Nombre',
      email: 'Correo electrónico',
      password: 'Contraseña',
      passwordConfirmation: 'Confirmar contraseña',
      submit: 'Crear cuenta',
      existingAccount: '¿Ya tienes una cuenta?',
    },
    password: {
      forgotTitle: 'Restablecer tu contraseña',
      forgotDescription:
        'Introduce tu correo y te enviaremos un enlace para restablecer la contraseña.',
      sendResetLink: 'Enviar enlace de restablecimiento',
      resetTitle: 'Elige una nueva contraseña',
      resetSubmit: 'Restablecer contraseña',
      confirmTitle: 'Confirmar contraseña',
    },
    verification: {
      title: 'Verifica tu correo electrónico',
      resend: 'Reenviar correo de verificación',
    },
    twoFactor: {
      title: 'Autenticación de dos factores',
      code: 'Código de autenticación',
      recoveryCode: 'Código de recuperación',
      submit: 'Continuar',
    },
    invitation: {
      title: 'Invitación de alianza',
      accept: 'Aceptar invitación',
    },
  },
  authExperience: {
    shell: {
      headline: 'Hecho para líderes de alianza.',
      intro:
        'Acceso seguro a las herramientas que tu alianza usa para coordinarse, reclutar y prepararse para lo que viene.',
    },
    login: {
      intro: 'Accede a todas las alianzas vinculadas a tu cuenta global.',
      invitationNotice:
        'Inicia sesión con la cuenta invitada para continuar aceptando la invitación de alianza.',
      needAccount: '¿Necesitas una cuenta?',
      register: 'Registrarse',
    },
    register: {
      intro: 'Una identidad global puede pertenecer a varias alianzas.',
      invitationNotice:
        'Te invitaron a {alliance} como {email}. Crear tu cuenta también aceptará esta invitación.',
      invitationOnly:
        'El registro está disponible solo por invitación. Abre el enlace enviado por tu alianza.',
      timezone: 'Zona horaria',
      passwordHint: 'Al menos 12 caracteres con mayúsculas, minúsculas y un número.',
      existingAccount: '¿Ya tienes una cuenta?',
    },
    invitation: {
      join: 'Unirse a {alliance}',
      forEmail: 'Esta invitación es para {email}.',
      expires: 'Vence el {date}',
      wrongAccount:
        'Has iniciado sesión como {email}. Inicia sesión con el correo invitado para aceptar esta invitación.',
      createAndJoin: 'Crear cuenta y unirse',
      signInAccept: 'Iniciar sesión para aceptar',
    },
    password: {
      backToSignIn: 'Volver a iniciar sesión',
      resetIntro: 'Restablecer tu contraseña revoca los tokens de acceso personales.',
      newPassword: 'Nueva contraseña',
      confirmNewPassword: 'Confirmar nueva contraseña',
      confirmDescription:
        'Esta acción cambia el acceso o los permisos de alianza, por lo que debes confirmar de nuevo tu contraseña.',
      recoveryKicker: 'RECUPERACIÓN DE CUENTA',
      recoveryNeutral:
        'Si existe una credencial de contraseña elegible para ese correo electrónico, le enviaremos instrucciones seguras para restablecer la contraseña.',
      checkInboxKicker: 'Comprueba tu bandeja de entrada',
      checkInboxHeading: 'Instrucciones de recuperación solicitadas',
      checkInboxDescription:
        'Si existe una contraseña válida para la dirección que ingresó, se han enviado las instrucciones para restablecer la contraseña. Por razones de seguridad, no podemos confirmar si una cuenta existe o qué métodos utiliza.',
      resetKicker: 'Restablecimiento de contraseña seguro',
      accountEmail: 'Correo electrónico de la cuenta',
      requirements: 'Su contraseña debe incluir:',
      requirementLength: 'Al menos 12 caracteres',
      requirementUpper: 'Una letra mayúscula',
      requirementLower: 'Una letra minúscula',
      requirementNumber: 'Un número',
      showPassword: 'Mostrar contraseña *',
      hidePassword: 'Ocultar contraseña',
      validationSummary: 'Revise los campos resaltados antes de continuar.',
    },
    verification: {
      description:
        'Enviamos un enlace de verificación a la dirección de correo que aparece abajo. Verifica la dirección antes de realizar acciones protegidas.',
      sent: 'Se ha enviado un nuevo enlace de verificación.',
      kicker: 'Verificación de correo electrónico',
      instructions:
        'Abre el mensaje y sigue el enlace para finalizar la verificación de tu dirección de correo electrónico.',
      securityNote:
        'Kingshot Alliance nunca le pedirá su contraseña a través de un mensaje de verificación por correo electrónico.',
      resendIn: 'Puedes volver a enviar en {seconds}s',
      useAnotherAccount: 'Cerrar sesión y usar otra cuenta',
    },
    twoFactor: {
      kicker: 'Comprobación de seguridad',
      description: 'Introduce el código actual de seis dígitos de tu aplicación de autenticación.',
      verifyCode: 'Verificar código',
      useRecoveryCode: 'Usar código de recuperación',
    },
    social: {
      continueWithGoogle: 'Continuar con Google',
      createWithGoogle: 'Crear cuenta Kingshot Alliance con Google',
      or: 'o',
    },
    passkeys: {
      signIn: 'Iniciar sesión con una clave de acceso',
      authenticating: 'Comprobando la clave de acceso…',
    },
    confirm: {
      title: 'Confirmar contraseña',
      description:
        'Utilice uno de los métodos de inicio de sesión adjuntos antes de continuar con este cambio de cuenta confidencial.',
      usePassword: 'Confirmar con contraseña',
      useGoogle: 'Confirmar con Google',
      usePasskey: 'Confirmar con una clave de acceso',
    },
  },
} satisfies MessageCatalogue;

export default messages;
