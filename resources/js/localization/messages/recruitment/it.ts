import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    searchChoices: 'Cerca opzioni',
    findChoices: 'Cerca',
    noChoices: 'Nessuna opzione corrispondente.',
    choiceLookupFailed: 'Impossibile caricare le opzioni. Riprova.',
    activeConfigurationLimit:
      'Fino a {limit} elementi attivi. Disattivane uno per aggiungerne un altro.',
    historyItemsOnPage: '{count} record in questa pagina (fino a {pageSize}).',
    bulkOutcome: {
      'permission-denied': 'Permesso negato',
    },
    eyebrow: 'Reclutamento alleanza',
    title: 'Reclutamento',
    candidates: 'Candidati',
    accepted: 'Accettati',
    joined: 'Entrati',
    pipeline: 'Reclutamento',
    backToPipeline: 'Torna al reclutamento',
    stage: 'Fase',
    source: 'Fonte',
    submitted: 'Inviato',
    nextAction: 'Prossima azione',
    bulkActions: 'Modifiche fase candidati',
    selectedCandidates: '{count} candidati selezionati',
    bulkPreviewHelp:
      'Verifica chi può cambiare fase prima di applicare la modifica. I candidati non idonei restano invariati.',
    previewBulkAction: 'Verifica cambio fase',
    bulkPreview: 'Anteprima cambio fase',
    bulkPreviewSummary:
      '{ready} possono essere aggiornati e {blocked} richiedono verifica o sono già nella fase di destinazione.',
    confirmBulkTitle: 'Conferma cambio fase',
    confirmBulkDescription: 'Spostare {count} candidati idonei in {stage}?',
    confirmBulkAction: 'Aggiorna candidati idonei',
    bulkResult: 'Risultato cambio fase',
    bulkResultSummary:
      '{succeeded} aggiornati. {failed} richiedono verifica. {skipped} erano già aggiornati.',
    failedItemsSelected:
      'I candidati che non è stato possibile aggiornare restano selezionati per la verifica.',
    settings: 'Impostazioni candidatura',
    questions: 'Domande candidatura',
    onboarding: 'Checklist onboarding',
    choosePlayer: 'Seleziona Governatore',
    privateNotes: 'Note private reclutatore',
    stageHistory: 'Cronologia fasi',
  },
} satisfies MessageCatalogue;

export default messages;
