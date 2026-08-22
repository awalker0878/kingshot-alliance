import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
