import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: 'Königreichstransfer',
    title: 'Transferplanung',
    readinessBoard: 'Bereitschaft',
    completion: 'Ergebnis',
    manageTransfers: 'Transfers verwalten',
    currentCycle: 'Aktueller Zyklus',
    participants: 'Teilnehmer',
    incoming: 'Eingehend',
    outgoing: 'Ausgehend',
    staying: 'Bleibt',
    transferGroups: 'Transfergruppen',
    player: 'Gouverneur',
    gamePlayerId: 'Spiel-ID des Gouverneurs',
    readinessTitle: 'Transferbereitschaft',
    completionTitle: 'Transferergebnis',
    recordCompletion: 'Transferergebnis erfassen',
    rosterHandoffRecorded: 'Allianz-Kader aktualisiert',
    completedStatus: 'Abgeschlossen',
    notCompletedStatus: 'Nicht abgeschlossen',
    stateDraft: 'Entwurf',
    stateOpen: 'Offen',
    stateLocked: 'Gesperrt',
    stateClosed: 'Geschlossen',
    stateCancelled: 'Abgebrochen',
    readinessReady: 'Bereit',
    readinessBlocked: 'Blockiert',
    readinessConfirmed: 'Bestätigt',
  },
} satisfies MessageCatalogue;

export default messages;
