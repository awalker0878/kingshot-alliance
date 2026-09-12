import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
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
