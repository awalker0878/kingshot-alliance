import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: 'Transferencia de reino',
    title: 'Planificación de transferencia',
    readinessBoard: 'Preparación',
    completion: 'Resultado',
    manageTransfers: 'Gestionar transferencias',
    currentCycle: 'Ciclo actual',
    participants: 'Participantes',
    incoming: 'Entrante',
    outgoing: 'Saliente',
    staying: 'Se queda',
    transferGroups: 'Grupos de transferencia',
    player: 'Gobernador',
    gamePlayerId: 'ID de juego del Gobernador',
    readinessTitle: 'Preparación para la transferencia',
    completionTitle: 'Resultado de la transferencia',
    recordCompletion: 'Registrar resultado de la transferencia',
    rosterHandoffRecorded: 'Plantilla de la alianza actualizada',
    completedStatus: 'Completado',
    notCompletedStatus: 'No completado',
    stateDraft: 'Borrador',
    stateOpen: 'Abierto',
    stateLocked: 'Bloqueado',
    stateClosed: 'Cerrado',
    stateCancelled: 'Cancelado',
    readinessReady: 'Listo',
    readinessBlocked: 'Bloqueado',
    readinessConfirmed: 'Confirmado',
  },
} satisfies MessageCatalogue;

export default messages;
