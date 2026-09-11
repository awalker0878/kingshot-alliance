import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    participantPagination: 'Pagine dei partecipanti',
    participantPageSummary: '{count} partecipanti in questa pagina; {total} in questa vista.',
    participantPageOrder:
      'Ordinati per ID di registrazione stabile. I totali comprendono l’intera vista.',
    participantPageUnavailable:
      'Impossibile caricare la pagina dei partecipanti. La pagina visualizzata non è cambiata.',
    retryParticipantPage: 'Riprova',
    participantFilterPageOnly:
      'Questo filtro di idoneità si applica alla pagina corrente. Scorri le pagine per esaminare tutti i partecipanti.',
    workflowHistoryUnavailable: 'Impossibile caricare la cronologia del trasferimento.',
    workflowHistoryTotal: '{count} voci in questa cronologia.',
    observationHistoryUnavailable: 'Impossibile caricare la cronologia delle osservazioni.',
    reloadHistory: 'Ricarica cronologia',
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
