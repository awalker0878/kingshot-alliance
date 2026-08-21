import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Lingua',
    signIn: 'Accedi',
    signOut: 'Esci',
    createAccount: 'Crea account',
    continue: 'Continua',
    cancel: 'Annulla',
    save: 'Salva',
    close: 'Chiudi',
    loading: 'Caricamento',
    openNavigation: 'Apri navigazione',
    closeNavigation: 'Chiudi navigazione',
    playerAlliance: 'Alleanza del Governatore attivo',
    noPlayerAlliance: 'Il Governatore attivo non fa attualmente parte di un’Alleanza.',
    skipToContent: 'Vai al contenuto',
  },
  navigation: {
    alliance: 'Alleanza',
    events: 'Eventi',
    roster: 'Membri dell’Alleanza',
    recruitment: 'Reclutamento',
    content: 'Bacheca',
    contributions: 'Contributi dell’Alleanza',
    kingdom: 'Alleanze del Regno',
    transfers: 'Trasferimento di Regno',
    integrations: 'Connessioni',
    profile: 'Account Governatore',
    settings: 'Impostazioni',
    allianceOperations: 'Alleanza',
    kingdomOperations: 'Regno',
  },
  application: {
    dashboard: {
      eyebrow: 'La tua Alleanza',
      welcome: 'Benvenuto, Governatore {name}',
      verificationPending: 'Verifica e-mail in sospeso',
      playerContextTitle: 'Governatore attivo',
      playerContextIntro:
        'Cambia Governatore per cambiare l’identità Kingshot usata per le azioni di Alleanza e Regno.',
      playerKingdom: 'Regno #{kingdom}',
      playerAuthorityIntro:
        'Grado dell’Alleanza, ruoli, incarichi del Regno e accesso agli Eventi seguono il Governatore attivo.',
      selectPlayer: 'Seleziona Governatore',
      playerAllianceTitle: 'Alleanza del Governatore attivo',
      playerAllianceIntro: 'L’accesso all’Alleanza segue grado e ruoli del Governatore attivo.',
      noPlayerAllianceTitle: 'Questo Governatore non fa parte di un’Alleanza',
      noPlayerAllianceIntro:
        'Cambia Governatore, unisciti a un’Alleanza o crea un’Alleanza per usare le funzioni dell’Alleanza.',
      openPlayerAlliance: 'Apri Alleanza',
      active: 'Attiva',
      roles: 'Ruoli dell’Alleanza',
      kingdomAlliances: 'Alleanze del Regno',
      transfers: 'Trasferimento di Regno',
      kingdomSettings: 'Impostazioni del Regno',
      createTitle: 'Crea un’Alleanza',
      createIntro:
        'Crea un’Alleanza per il Governatore attivo. L’Alleanza usa il Regno di quel Governatore e il Governatore fondatore diventa R5.',
      allianceName: 'Nome Alleanza',
      timezone: 'Fuso orario dell’Alleanza',
      create: 'Crea Alleanza',
    },
  },
} satisfies MessageCatalogue;

export default messages;
