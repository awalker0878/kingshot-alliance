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
    playerAlliance: 'Alianza del jugador activo',
    noPlayerAlliance: 'El jugador activo no tiene una membresía de alianza activa.',
    skipToContent: 'Saltar al contenido',
  },
  navigation: {
    home: 'Inicio',
    dashboard: 'Panel',
    alliance: 'Alianza',
    events: 'Eventos',
    roster: 'Miembros',
    recruitment: 'Reclutamiento',
    content: 'Contenido',
    contributions: 'Contribuciones',
    kingdom: 'Reino',
    transfers: 'Traslados',
    integrations: 'Integraciones',
    profile: 'Perfil',
    settings: 'Ajustes',
    allianceOperations: 'Operaciones de alianza',
    kingdomOperations: 'Operaciones del reino',
    account: 'Cuenta',
  },
  application: {
    dashboard: {
      title: 'Panel',
      eyebrow: 'Mando de la alianza',
      welcome: 'Bienvenido, {name}',
      verificationPending: 'Verificación de correo pendiente',
      playerContextTitle: 'Jugador activo',
      playerContextIntro:
        'Cambiar de jugador cambia la identidad de juego usada para la autoridad de alianza y reino.',
      playerKingdom: 'Reino #{kingdom}',
      playerAuthorityIntro:
        'La membresía, el rango, los roles, los permisos de reino y las acciones de juego se resuelven únicamente desde este jugador.',
      selectPlayer: 'Selecciona un jugador para usar las herramientas del juego.',
      playerAllianceTitle: 'Alianza del jugador activo',
      playerAllianceIntro:
        'Las herramientas de alianza usan únicamente la membresía, el rango y los roles del jugador activo.',
      noPlayerAllianceTitle: 'Este jugador no pertenece a una alianza',
      noPlayerAllianceIntro:
        'Cambia de jugador o crea/únete a una alianza con el jugador activo antes de abrir herramientas de alianza.',
      openPlayerAlliance: 'Abrir alianza del jugador',
      active: 'Activa',
      kingdomAlliances: 'Alianzas del reino',
      transfers: 'Transferencias',
      kingdomSettings: 'Ajustes del reino',
      createTitle: 'Crear una alianza',
      createIntro:
        'Crea una alianza para el jugador activo. El reino de la alianza se deriva de ese jugador, que se convierte en el R5 inicial.',
      allianceName: 'Nombre de la alianza',
      timezone: 'Zona horaria',
      create: 'Crear alianza',
    },
  },
} satisfies MessageCatalogue;

export default messages;
