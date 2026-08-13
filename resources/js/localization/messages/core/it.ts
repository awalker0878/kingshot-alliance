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
    currentAlliance: 'Alleanza attuale',
    noActiveAlliance: "Seleziona un'alleanza per aprire gli strumenti dell'alleanza.",
    skipToContent: 'Vai al contenuto',
  },
  navigation: {
    alliance: 'Alleanza',
    events: 'Eventi',
    roster: 'Membri',
    recruitment: 'Reclutamento',
    content: 'Contenuti',
    contributions: 'Contributi',
    kingdom: 'Regno',
    transfers: 'Trasferimenti',
    integrations: 'Integrazioni',
    profile: 'Profilo',
    settings: 'Impostazioni',
    allianceOperations: 'Operazioni alleanza',
    kingdomOperations: 'Operazioni regno',
  },
  application: {
    dashboard: {
      eyebrow: 'Comando alleanza',
      welcome: 'Benvenuto, {name}',
      verificationPending: 'Verifica e-mail in sospeso',
      activeAllianceTitle: 'Alleanza attiva',
      activeAllianceIntro:
        'Questa alleanza è il contesto corrente per gli strumenti dell’alleanza.',
      noActiveAllianceTitle: 'Scegli un’alleanza attiva',
      noActiveAllianceIntro:
        'Seleziona una delle tue appartenenze prima di aprire gli strumenti di alleanza, eventi, roster, regno o trasferimenti.',
      alliancesTitle: 'Le tue alleanze',
      alliancesIntro: 'Scegli quale alleanza usare come contesto di lavoro corrente.',
      openActiveAlliance: 'Apri alleanza attiva',
      active: 'Attiva',
      roles: 'Ruoli',
      noRoles: 'Nessun ruolo assegnato',
      switchAlliance: 'Passa a questa alleanza',
      kingdomAlliances: 'Alleanze del regno',
      transfers: 'Trasferimenti',
      kingdomSettings: 'Impostazioni regno',
      empty:
        'Non hai ancora un’appartenenza attiva a un’alleanza. Crea un’alleanza qui sotto per stabilire il tuo primo contesto.',
      createTitle: 'Crea un’alleanza',
      createIntro:
        'Crea una nuova alleanza e diventa il suo proprietario iniziale in un’unica operazione.',
      allianceName: 'Nome alleanza',
      kingdomNumber: 'Numero regno',
      timezone: 'Fuso orario',
      create: 'Crea alleanza',
    },
  },
} satisfies MessageCatalogue;

export default messages;
