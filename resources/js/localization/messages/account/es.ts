import type { MessageCatalogue } from '../../types';

const messages = {
  accountExperience: {
    account: {
      eyebrow: 'Control de cuenta',
      title: 'Cuenta y seguridad',
      intro:
        'Gestiona tu identidad, verificación, contraseña, autenticación de dos factores y sesiones activas.',
      passwordUpdated: 'Tu contraseña se actualizó y se cerraron las demás sesiones autenticadas.',
      sessionsRevoked: 'Se cerraron las demás sesiones autenticadas.',
      twoFactorDisabled: 'La autenticación de dos factores fue desactivada.',
      profileTitle: 'Perfil',
      profileIntro: 'Cambiar tu correo electrónico requiere verificarlo de nuevo.',
      timezone: 'Zona horaria',
      saveProfile: 'Guardar perfil',
      emailVerification: 'Verificación de correo',
      verified: 'Verificado',
      pending: 'Pendiente',
      twoFactorState: 'Autenticación de dos factores',
      enabled: 'Activada',
      setupPending: 'Configuración pendiente',
      notEnabled: 'No activada',
      twoFactorTitle: 'Autenticación de dos factores',
      twoFactorIntro:
        'Protege el inicio de sesión con un autenticador TOTP. Los códigos de recuperación solo se muestran al crearlos o regenerarlos.',
      startSetup: 'Iniciar configuración',
      authenticatorSecret: 'Secreto del autenticador',
      provisioningUri: 'URI de aprovisionamiento',
      authenticationCode: 'Código de autenticación',
      confirm: 'Confirmar',
      saveRecoveryCodes: 'Guarda estos códigos de recuperación ahora',
      recoveryIntro: 'Cada código funciona una sola vez. Guárdalos por separado de esta cuenta.',
      regenerateRecoveryCodes: 'Regenerar códigos de recuperación',
      disableTwoFactor: 'Desactivar autenticación de dos factores',
      passwordTitle: 'Cambiar contraseña',
      passwordIntro:
        'Cambiar la contraseña revoca los tokens de acceso personales e invalida otras sesiones autenticadas.',
      currentPassword: 'Contraseña actual',
      newPassword: 'Nueva contraseña',
      confirmNewPassword: 'Confirmar nueva contraseña',
      updatePassword: 'Actualizar contraseña',
      sessionsTitle: 'Otras sesiones',
      sessionsIntro: 'Revoca todas las sesiones autenticadas excepto este dispositivo.',
      signOutOthers: 'Cerrar sesión en otros dispositivos',
      dangerTitle: 'Zona de riesgo',
      deleteAccount: 'Eliminar cuenta',
    },
    deletion: {
      eyebrow: 'Ciclo de vida de la cuenta',
      title: 'Eliminar cuenta',
      intro:
        'La eliminación tiene un periodo de espera de siete días. La propiedad activa de una alianza, el acceso de administrador de plataforma y las retenciones legales pueden bloquear el proceso. Las cuentas procesadas se anonimizan en lugar de borrar el historial de auditoría.',
      currentRequest: 'Solicitud actual',
      status: 'Estado',
      eligibleAt: 'Elegible el',
      requestedAt: 'Solicitado',
      processedAt: 'Procesado',
      notYet: 'Todavía no',
      requestTitle: 'Solicitar eliminación',
      requestIntro:
        'Transfiere primero la propiedad de cualquier alianza. Los registros sujetos a retención legal o necesarios para seguridad y auditoría se conservan seudonimizados.',
      requestButton: 'Solicitar eliminación de cuenta',
      confirm:
        '¿Solicitar la eliminación de la cuenta? Hay un periodo de espera de siete días y se aplican comprobaciones de propiedad y retención legal.',
      requested: 'Se registró tu solicitud de eliminación de cuenta.',
      backToAccount: 'Volver a cuenta y seguridad',
    },
  },
} satisfies MessageCatalogue;

export default messages;
