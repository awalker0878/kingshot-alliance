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
    playerAlliance: 'Allianz des aktiven Spielers',
    noPlayerAlliance: 'Der aktive Spieler hat keine aktive Allianzmitgliedschaft.',
    skipToContent: 'Zum Inhalt springen',
  },
  navigation: {
    home: 'Startseite',
    dashboard: 'Übersicht',
    alliance: 'Allianz',
    events: 'Ereignisse',
    roster: 'Mitglieder',
    recruitment: 'Rekrutierung',
    content: 'Inhalte',
    contributions: 'Beiträge',
    kingdom: 'Königreich',
    integrations: 'Integrationen',
    profile: 'Profil',
    settings: 'Einstellungen',
    allianceOperations: 'Allianzverwaltung',
    kingdomOperations: 'Königreich',
    account: 'Konto',
  },
  application: {
    dashboard: {
      title: 'Übersicht',
      eyebrow: 'Allianzführung',
      welcome: 'Willkommen, {name}',
      verificationPending: 'E-Mail-Bestätigung ausstehend',
      playerContextTitle: 'Aktiver Spieler',
      playerContextIntro:
        'Ein Spielerwechsel ändert die Spielidentität für Allianz- und Königreichsberechtigungen.',
      playerKingdom: 'Königreich #{kingdom}',
      playerAuthorityIntro:
        'Allianzmitgliedschaft, Rang, Rollen, Königreichsrechte und Spielaktionen werden nur aus diesem Spieler abgeleitet.',
      selectPlayer: 'Wähle einen Spieler für die Spiel-Werkzeuge aus.',
      playerAllianceTitle: 'Allianz des aktiven Spielers',
      playerAllianceIntro:
        'Allianz-Werkzeuge verwenden nur Mitgliedschaft, Rang und Rollen des aktiven Spielers.',
      noPlayerAllianceTitle: 'Dieser Spieler ist in keiner Allianz',
      noPlayerAllianceIntro:
        'Wechsle den Spieler oder erstelle/betrete mit dem aktiven Spieler eine Allianz, bevor du Allianz-Werkzeuge öffnest.',
      openPlayerAlliance: 'Allianz des Spielers öffnen',
      active: 'Aktiv',
      roles: 'Rollen',
      roster: 'Kader',
      kingdomAlliances: 'Königreich-Allianzen',
      kingdomSettings: 'Königreich-Einstellungen',
      createTitle: 'Allianz erstellen',
      createIntro:
        'Erstelle eine Allianz für den aktiven Spieler. Das Königreich der Allianz wird von diesem Spieler abgeleitet, der zum ersten R5 wird.',
      allianceName: 'Allianzname',
      timezone: 'Zeitzone',
      create: 'Allianz erstellen',
    },
  },
} satisfies MessageCatalogue;

export default messages;
