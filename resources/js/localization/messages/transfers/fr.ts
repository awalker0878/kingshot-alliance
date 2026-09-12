import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Rechercher des options',
    choicePageSummary:
      '{count} options sur cette page ; {total} correspondent. Ordre par identifiant stable.',
    choiceUnavailable: 'L’option sélectionnée n’est plus disponible.',
    choicesFailed:
      'Impossible de charger les options. Votre sélection et la page affichée sont conservées.',
    retryChoices: 'Réessayer',

    participantPagination: 'Pages des participants',
    participantPageSummary: '{count} participants sur cette page ; {total} dans cette vue.',
    participantPageOrder:
      'Tri par identifiant stable d’inscription. Les totaux couvrent toute la vue.',
    participantPageUnavailable:
      'Impossible de charger la page des participants. La page affichée reste inchangée.',
    retryParticipantPage: 'Réessayer',
    participantFilterPageOnly:
      'Ce filtre d’admissibilité s’applique à la page actuelle. Parcourez les pages pour examiner tous les participants.',
    workflowHistoryUnavailable: 'Impossible de charger l’historique du transfert.',
    workflowHistoryTotal: '{count} entrées dans cet historique.',
    observationHistoryUnavailable: 'Impossible de charger l’historique des observations.',
    reloadHistory: 'Recharger l’historique',
    eyebrow: 'Transfert de royaume',
    title: 'Planification du transfert',
    readinessBoard: 'Préparation',
    completion: 'Résultat',
    manageTransfers: 'Gérer les transferts',
    currentCycle: 'Cycle actuel',
    participants: 'Participants',
    incoming: 'Entrant',
    outgoing: 'Sortant',
    staying: 'Reste',
    transferGroups: 'Groupes de transfert',
    player: 'Gouverneur',
    gamePlayerId: 'ID de jeu du Gouverneur',
    readinessTitle: 'Préparation au transfert',
    completionTitle: 'Résultat du transfert',
    recordCompletion: 'Enregistrer le résultat du transfert',
    rosterHandoffRecorded: 'Effectif de l’alliance mis à jour',
    completedStatus: 'Terminé',
    notCompletedStatus: 'Non terminé',
    stateDraft: 'Brouillon',
    stateOpen: 'Ouvert',
    stateLocked: 'Verrouillé',
    stateClosed: 'Fermé',
    stateCancelled: 'Annulé',
    readinessReady: 'Prêt',
    readinessBlocked: 'Bloqué',
    readinessConfirmed: 'Confirmé',
  },
} satisfies MessageCatalogue;

export default messages;
