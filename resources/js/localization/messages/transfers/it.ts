import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: 'Trasferimento Regno',
    title: 'Pianificazione trasferimento',
    readinessBoard: 'Preparazione',
    completion: 'Risultato',
    manageTransfers: 'Gestisci trasferimenti',
    currentCycle: 'Ciclo attuale',
    participants: 'Partecipanti',
    incoming: 'In entrata',
    outgoing: 'In uscita',
    staying: 'Resta',
    transferGroups: 'Gruppi di trasferimento',
    player: 'Governatore',
    gamePlayerId: 'ID di gioco del Governatore',
    readinessTitle: 'Preparazione al trasferimento',
    completionTitle: 'Risultato del trasferimento',
    recordCompletion: 'Registra risultato del trasferimento',
    rosterHandoffRecorded: 'Roster dell’alleanza aggiornato',
    completedStatus: 'Completato',
    notCompletedStatus: 'Non completato',
    readinessReady: 'Pronto',
    readinessBlocked: 'Bloccato',
    readinessConfirmed: 'Confermato',
  },
} satisfies MessageCatalogue;

export default messages;
