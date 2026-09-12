import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} enregistrements',
    historyUnavailable: 'Impossible de charger cette page.',
    retryPage: 'Réessayer la page',
    title: 'Administration de la plateforme',
    backDashboard: 'Retour à l’accueil',
    capacityTitle: 'Capacité et opérations',
    administrators: 'Administrateurs de plateforme',
    provisionAlliance: 'Créer une Alliance',
    allianceFleet: 'Alliances',
    legalHolds: 'Protection des enregistrements',
    localizationRuntime: 'Langues',
  },
} satisfies MessageCatalogue;

export default messages;
