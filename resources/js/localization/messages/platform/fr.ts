import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'L’option sélectionnée n’est plus disponible.',
    searchChoices: 'Rechercher des options',
    choicePageSummary:
      '{count} options sur cette page ; {total} correspondent. Ordre par identifiant stable.',
    choicesFailed:
      'Impossible de charger les options. Votre sélection et la page affichée sont conservées.',
    retryChoices: 'Réessayer',
    recoveryFailed: 'La récupération a échoué. Veuillez réessayer.',
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
