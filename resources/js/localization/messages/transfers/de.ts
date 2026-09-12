import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Optionen suchen',
    choicePageSummary:
      '{count} Optionen auf dieser Seite; {total} Treffer. Nach stabiler Datensatz-ID sortiert.',
    choiceUnavailable: 'Die gewählte Option ist nicht mehr verfügbar.',
    choicesFailed:
      'Optionen konnten nicht geladen werden. Auswahl und angezeigte Seite bleiben unverändert.',
    retryChoices: 'Optionen erneut laden',

    participantPagination: 'Teilnehmerseiten',
    participantPageSummary: '{count} Teilnehmer auf dieser Seite; {total} in dieser Ansicht.',
    participantPageOrder:
      'Nach stabiler Registrierungs-ID sortiert. Summen gelten für die gesamte Ansicht.',
    participantPageUnavailable:
      'Die Teilnehmerseite konnte nicht geladen werden. Die angezeigte Seite bleibt unverändert.',
    retryParticipantPage: 'Seite erneut laden',
    participantFilterPageOnly:
      'Dieser Eignungsfilter gilt für die aktuelle Seite. Blättere weiter, um alle Teilnehmer zu prüfen.',
    workflowHistoryUnavailable: 'Der Verlauf des Transferablaufs konnte nicht geladen werden.',
    workflowHistoryTotal: '{count} Einträge in diesem Verlauf.',
    observationHistoryUnavailable: 'Der Beobachtungsverlauf konnte nicht geladen werden.',
    reloadHistory: 'Verlauf neu laden',
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
