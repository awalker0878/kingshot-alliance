import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Idioma',
    signIn: 'Iniciar sesión',
    signOut: 'Cerrar sesión',
    createAccount: 'Crear cuenta',
    continue: 'Continuar',
    cancel: 'Cancelar',
    save: 'Guardar',
    close: 'Cerrar',
    loading: 'Cargando',
    menu: 'Menú',
    openNavigation: 'Abrir navegación',
    closeNavigation: 'Cerrar navegación',
    playerAlliance: 'Alianza del Gobernador activo',
    noPlayerAlliance: 'El Gobernador activo no está actualmente en una Alianza.',
    skipToContent: 'Saltar al contenido',
  },
  navigation: {
    home: 'Inicio',
    dashboard: 'Resumen de la Alianza',
    alliance: 'Alianza',
    events: 'Eventos',
    roster: 'Miembros de la Alianza',
    recruitment: 'Reclutamiento',
    content: 'Tablón de anuncios',
    contributions: 'Contribuciones de la Alianza',
    kingdom: 'Alianzas del Reino',
    transfers: 'Transferencia de Reino',
    integrations: 'Conexiones',
    profile: 'Cuenta del Gobernador',
    settings: 'Ajustes',
    allianceOperations: 'Alianza',
    kingdomOperations: 'Reino',
    account: 'Cuenta del Gobernador',
  },
  application: {
    dashboard: {
      title: 'Resumen de la Alianza',
      eyebrow: 'Tu Alianza',
      welcome: 'Bienvenido, Gobernador {name}',
      verificationPending: 'Verificación de correo pendiente',
      playerContextTitle: 'Gobernador activo',
      playerContextIntro:
        'Cambia de Gobernador para cambiar la identidad de Kingshot usada en las acciones de Alianza y Reino.',
      playerKingdom: 'Reino #{kingdom}',
      playerAuthorityIntro:
        'El rango de Alianza, los roles, las funciones del Reino y el acceso a Eventos siguen al Gobernador activo.',
      selectPlayer: 'Seleccionar Gobernador',
      playerAllianceTitle: 'Alianza del Gobernador activo',
      playerAllianceIntro: 'El acceso a la Alianza sigue el rango y los roles del Gobernador activo.',
      noPlayerAllianceTitle: 'Este Gobernador no está en una Alianza',
      noPlayerAllianceIntro:
        'Cambia de Gobernador, únete a una Alianza o crea una Alianza para usar las funciones de Alianza.',
      openPlayerAlliance: 'Abrir Alianza',
      active: 'Activa',
      kingdomAlliances: 'Alianzas del Reino',
      transfers: 'Transferencia de Reino',
      kingdomSettings: 'Ajustes del Reino',
      createTitle: 'Crear una Alianza',
      createIntro:
        'Crea una Alianza para el Gobernador activo. La Alianza usa el Reino de ese Gobernador y el Gobernador fundador se convierte en R5.',
      allianceName: 'Nombre de la Alianza',
      timezone: 'Zona horaria de la Alianza',
      create: 'Crear Alianza',
    },
  },
} satisfies MessageCatalogue;

export default messages;
