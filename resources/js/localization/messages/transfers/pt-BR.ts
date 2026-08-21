import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: 'Transferência de Reino',
    title: 'Planejamento de transferência',
    readinessBoard: 'Preparação',
    completion: 'Resultado',
    manageTransfers: 'Gerenciar transferências',
    currentCycle: 'Ciclo atual',
    participants: 'Participantes',
    incoming: 'Entrada',
    outgoing: 'Saída',
    staying: 'Permanece',
    transferGroups: 'Grupos de transferência',
    player: 'Governador',
    gamePlayerId: 'ID do jogo do Governador',
    readinessTitle: 'Preparação para transferência',
    completionTitle: 'Resultado da transferência',
    recordCompletion: 'Registrar resultado da transferência',
    rosterHandoffRecorded: 'Lista da aliança atualizada',
    completedStatus: 'Concluído',
    notCompletedStatus: 'Não concluído',
    stateDraft: 'Rascunho',
    stateOpen: 'Aberto',
    stateLocked: 'Bloqueado',
    stateClosed: 'Fechado',
    stateCancelled: 'Cancelado',
    readinessReady: 'Pronto',
    readinessBlocked: 'Bloqueado',
    readinessConfirmed: 'Confirmado',
  },
} satisfies MessageCatalogue;

export default messages;
