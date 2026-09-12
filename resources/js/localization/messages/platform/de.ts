import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Die gewählte Option ist nicht mehr verfügbar.',
    searchChoices: 'Optionen suchen',
    choicePageSummary:
      '{count} Optionen auf dieser Seite; {total} Treffer. Nach stabiler Datensatz-ID sortiert.',
    choicesFailed:
      'Optionen konnten nicht geladen werden. Auswahl und angezeigte Seite bleiben unverändert.',
    retryChoices: 'Optionen erneut laden',
    recoveryFailed: 'Die Wiederherstellung ist fehlgeschlagen. Bitte erneut versuchen.',
    recordCount: '{count} Einträge',
    historyUnavailable: 'Diese Seite konnte nicht geladen werden.',
    retryPage: 'Seite erneut laden',
    title: 'Plattformverwaltung',
    backDashboard: 'Zurück zur Startseite',
    capacityTitle: 'Kapazität und Betrieb',
    administrators: 'Plattformadministratoren',
    provisionAlliance: 'Allianz erstellen',
    allianceFleet: 'Allianzen',
    legalHolds: 'Datensatzsperren',
    localizationRuntime: 'Sprachen',
  },
} satisfies MessageCatalogue;

export default messages;
