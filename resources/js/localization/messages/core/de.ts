import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Sprache',
    signIn: 'Anmelden',
    signOut: 'Abmelden',
    createAccount: 'Konto erstellen',
    continue: 'Weiter',
    cancel: 'Abbrechen',
    save: 'Speichern',
    close: 'Schließen',
    loading: 'Laden',
    menu: 'Menü',
    openNavigation: 'Navigation öffnen',
    closeNavigation: 'Navigation schließen',
    playerAlliance: 'Allianz des aktiven Gouverneurs',
    noPlayerAlliance: 'Der aktive Gouverneur ist derzeit in keiner Allianz.',
    skipToContent: 'Zum Inhalt springen',
  },
  navigation: {
    home: 'Startseite',
    dashboard: 'Allianzübersicht',
    alliance: 'Allianz',
    events: 'Events',
    roster: 'Allianzmitglieder',
    recruitment: 'Rekrutierung',
    content: 'Schwarzes Brett',
    contributions: 'Allianzbeiträge',
    kingdom: 'Königreich-Allianzen',
    integrations: 'Verbindungen',
    profile: 'Gouverneurskonto',
    settings: 'Einstellungen',
    allianceOperations: 'Allianz',
    kingdomOperations: 'Königreich',
    account: 'Gouverneurskonto',
  },
  application: {
    dashboard: {
      title: 'Allianzübersicht',
      eyebrow: 'Deine Allianz',
      welcome: 'Willkommen, Gouverneur {name}',
      verificationPending: 'E-Mail-Bestätigung ausstehend',
      playerContextTitle: 'Aktiver Gouverneur',
      playerContextIntro:
        'Wechsle den Gouverneur, um die Kingshot-Identität für Allianz- und Königreichsaktionen zu ändern.',
      playerKingdom: 'Königreich #{kingdom}',
      playerAuthorityIntro:
        'Allianzrang, Rollen, Königreichsaufgaben und Eventzugriff folgen dem aktiven Gouverneur.',
      selectPlayer: 'Gouverneur auswählen',
      playerAllianceTitle: 'Allianz des aktiven Gouverneurs',
      playerAllianceIntro: 'Der Allianzzugriff folgt Rang und Rollen des aktiven Gouverneurs.',
      noPlayerAllianceTitle: 'Dieser Gouverneur ist in keiner Allianz',
      noPlayerAllianceIntro:
        'Wechsle den Gouverneur, tritt einer Allianz bei oder erstelle eine Allianz, um Allianzfunktionen zu nutzen.',
      openPlayerAlliance: 'Allianz öffnen',
      active: 'Aktiv',
      roles: 'Allianzrollen',
      roster: 'Allianzmitglieder',
      kingdomAlliances: 'Königreich-Allianzen',
      kingdomSettings: 'Königreich-Einstellungen',
      createTitle: 'Allianz erstellen',
      createIntro:
        'Erstelle eine Allianz für den aktiven Gouverneur. Die Allianz verwendet das Königreich dieses Gouverneurs, und der Gründer wird R5.',
      allianceName: 'Allianzname',
      timezone: 'Allianz-Zeitzone',
      create: 'Allianz erstellen',
    },
  },
} satisfies MessageCatalogue;

export default messages;
