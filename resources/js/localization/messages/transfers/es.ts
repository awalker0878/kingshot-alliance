import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Buscar opciones',
    choicePageSummary:
      '{count} opciones en esta página; {total} coinciden. Ordenadas por identificador estable.',
    choiceUnavailable: 'La opción seleccionada ya no está disponible.',
    choicesFailed:
      'No se pudieron cargar las opciones. Se conservan la selección y la página mostrada.',
    retryChoices: 'Reintentar opciones',

    participantPagination: 'Páginas de participantes',
    participantPageSummary: '{count} participantes en esta página; {total} en esta vista.',
    participantPageOrder:
      'Ordenados por ID de registro estable. Los totales abarcan toda la vista.',
    participantPageUnavailable:
      'No se pudo cargar la página de participantes. La página mostrada no ha cambiado.',
    retryParticipantPage: 'Reintentar página',
    participantFilterPageOnly:
      'Este filtro de elegibilidad se aplica a la página actual. Recorre las páginas para revisar a todos los participantes.',
    workflowHistoryUnavailable: 'No se pudo cargar el historial del proceso de transferencia.',
    workflowHistoryTotal: '{count} registros en este historial.',
    observationHistoryUnavailable: 'No se pudo cargar el historial de observaciones.',
    reloadHistory: 'Recargar historial',
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
