import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Recrutement de l’alliance',
    title: 'Recrutement',
    candidates: 'Candidats',
    accepted: 'Acceptés',
    joined: 'Rejoints',
    pipeline: 'Recrutement',
    backToPipeline: 'Retour au recrutement',
    stage: 'Étape',
    submitted: 'Soumis',
    nextAction: 'Prochaine action',
    bulkActions: 'Changements d’étape des candidats',
    selectedCandidates: '{count} candidats sélectionnés',
    bulkPreviewHelp:
      'Vérifiez qui peut changer d’étape avant d’appliquer la modification. Les candidats non admissibles restent inchangés.',
    previewBulkAction: 'Vérifier le changement d’étape',
    bulkPreview: 'Aperçu du changement d’étape',
    bulkPreviewSummary:
      '{ready} peuvent être mis à jour et {blocked} nécessitent une vérification ou sont déjà à l’étape cible.',
    confirmBulkTitle: 'Confirmer le changement d’étape',
    confirmBulkDescription: 'Déplacer {count} candidats admissibles vers {stage} ?',
    confirmBulkAction: 'Mettre à jour les candidats admissibles',
    bulkResult: 'Résultat du changement d’étape',
    bulkResultSummary:
      '{succeeded} mis à jour. {failed} nécessitent une vérification. {skipped} étaient déjà à jour.',
    failedItemsSelected:
      'Les candidats qui n’ont pas pu être mis à jour restent sélectionnés pour vérification.',
    settings: 'Paramètres de candidature',
    questions: 'Questions de candidature',
    onboarding: 'Liste d’intégration',
    choosePlayer: 'Choisir un Gouverneur',
    privateNotes: 'Notes privées du recruteur',
    stageHistory: 'Historique des étapes',
  },
} satisfies MessageCatalogue;

export default messages;
