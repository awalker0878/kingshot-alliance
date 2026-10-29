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
      forgotTitle: 'Recuperar tu cuenta',
      forgotDescription:
        'Introduce el correo electrónico asociado a tu cuenta de Kingshot Alliance.',
      sendResetLink: 'Enviar instrucciones de recuperación',
      resetTitle: 'Elige una nueva contraseña',
      resetSubmit: 'Restablecer contraseña',
      confirmTitle: 'Confirma tu identidad',
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
        'Recluta Gobernadores, prepara Eventos, gestiona tu Alianza y sigue la actividad del Reino en un solo lugar.',
    },
    login: {
      intro: 'Inicia sesión con tu cuenta y luego elige el Gobernador que quieres usar.',
      invitationNotice:
        'Inicia sesión con la cuenta invitada para continuar aceptando la invitación de alianza.',
      needAccount: '¿Necesitas una cuenta?',
      register: 'Registrarse',
    },
    register: {
      intro:
        'Una cuenta puede tener varios Gobernadores. El rango en la Alianza y las funciones en el Reino siempre corresponden al Gobernador que elijas.',
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
      resetIntro:
        'Elige una nueva contraseña de Kingshot Alliance para la cuenta que aparece abajo.',
      newPassword: 'Nueva contraseña',
      confirmNewPassword: 'Confirmar nueva contraseña',
      confirmDescription:
        'Esto cambia el acceso protegido a la Alianza. Confirma tu identidad antes de continuar.',
      recoveryKicker: 'RECUPERACIÓN DE CUENTA',
      recoveryNeutral:
        'Si la recuperación de contraseña está disponible para ese correo electrónico, enviaremos instrucciones seguras para restablecerla.',
      checkInboxKicker: 'REVISA TU BANDEJA DE ENTRADA',
      checkInboxHeading: 'Instrucciones de recuperación solicitadas',
      checkInboxDescription:
        'Si la recuperación de contraseña está disponible para la dirección introducida, se han enviado instrucciones para restablecerla. Por seguridad, no podemos confirmar si existe una cuenta ni qué métodos de inicio de sesión utiliza.',
      resetKicker: 'RESTABLECIMIENTO SEGURO DE CONTRASEÑA',
      accountEmail: 'Correo electrónico de la cuenta',
      requirements: 'Su contraseña debe incluir:',
      requirementLength: 'Al menos 12 caracteres',
      requirementUpper: 'Una letra mayúscula',
      requirementLower: 'Una letra minúscula',
      requirementNumber: 'Un dígito',
      showPassword: 'Mostrar contraseña *',
      hidePassword: 'Ocultar contraseña',
      validationSummary: 'Revise los campos resaltados antes de continuar.',
    },
    verification: {
      description:
        'Enviamos un enlace de verificación seguro para tu cuenta de Kingshot Alliance a:',
      sent: 'Se ha enviado un nuevo enlace de verificación.',
      kicker: 'VERIFICACIÓN DEL CORREO ELECTRÓNICO',
      instructions:
        'Abre el mensaje y sigue el enlace para finalizar la verificación de tu dirección de correo electrónico.',
      securityNote:
        'Kingshot Alliance nunca te pedirá tu contraseña en un mensaje de verificación del correo electrónico.',
      resendIn: 'Podrás reenviar en {seconds} segundos',
      useAnotherAccount: 'Cerrar sesión y usar otra cuenta',
    },
    twoFactor: {
      kicker: 'Seguridad de la cuenta',
      description: 'Introduce el código actual de seis dígitos de tu aplicación de autenticación.',
      verifyCode: 'Verificar código',
      useRecoveryCode: 'Usar código de recuperación',
    },
    social: {
      continueWithGoogle: 'Continuar con Google',
      createWithGoogle: 'Crear una cuenta de Kingshot Alliance con Google',
      or: 'o',
    },
    passkeys: {
      signIn: 'Iniciar sesión con una clave de acceso',
      authenticating: 'Comprobando la clave de acceso…',
    },
    confirm: {
      title: 'Confirma tu identidad',
      description:
        'Usa uno de los métodos de inicio de sesión vinculados a tu cuenta antes de continuar con este cambio sensible en la cuenta.',
      usePassword: 'Confirmar con contraseña',
      useGoogle: 'Confirmar con Google',
      usePasskey: 'Confirmar con una clave de acceso',
    },
  },
} satisfies MessageCatalogue;

export default messages;
