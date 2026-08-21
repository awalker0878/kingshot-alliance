import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Allianz-Rekrutierung',
    title: 'Rekrutierung',
    candidates: 'Kandidaten',
    accepted: 'Angenommen',
    joined: 'Beigetreten',
    pipeline: 'Rekrutierung',
    backToPipeline: 'Zurück zur Rekrutierung',
    stage: 'Phase',
    source: 'Quelle',
    submitted: 'Eingereicht',
    nextAction: 'Nächste Aktion',
    bulkActions: 'Phasenänderungen für Kandidaten',
    selectedCandidates: '{count} Kandidaten ausgewählt',
    bulkPreviewHelp: 'Prüfe vor der Änderung, wer verschoben werden kann. Nicht berechtigte Kandidaten bleiben unverändert.',
    previewBulkAction: 'Phasenänderung prüfen',
    bulkPreview: 'Vorschau der Phasenänderung',
    bulkPreviewSummary: '{ready} können aktualisiert werden; {blocked} müssen geprüft werden oder sind bereits in der Zielphase.',
    confirmBulkTitle: 'Phasenänderung bestätigen',
    confirmBulkDescription: '{count} berechtigte Kandidaten nach {stage} verschieben?',
    confirmBulkAction: 'Berechtigte Kandidaten aktualisieren',
    bulkResult: 'Ergebnis der Phasenänderung',
    bulkResultSummary: '{succeeded} aktualisiert. {failed} müssen geprüft werden. {skipped} waren bereits aktuell.',
    failedItemsSelected: 'Kandidaten, die nicht aktualisiert werden konnten, bleiben zur Prüfung ausgewählt.',
    settings: 'Bewerbungseinstellungen',
    questions: 'Bewerbungsfragen',
    onboarding: 'Onboarding-Checkliste',
    choosePlayer: 'Governor auswählen',
    privateNotes: 'Private Recruiter-Notizen',
    stageHistory: 'Phasenverlauf',
  },
} satisfies MessageCatalogue;

export default messages;
