import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Reclutamiento de alianza',
    title: 'Reclutamiento',
    candidates: 'Candidatos',
    accepted: 'Aceptados',
    joined: 'Ingresaron',
    pipeline: 'Reclutamiento',
    backToPipeline: 'Volver a reclutamiento',
    stage: 'Etapa',
    source: 'Origen',
    submitted: 'Enviado',
    nextAction: 'Próxima acción',
    bulkActions: 'Cambios de etapa de candidatos',
    selectedCandidates: '{count} candidatos seleccionados',
    bulkPreviewHelp:
      'Revisa quién puede cambiar de etapa antes de aplicar el cambio. Los candidatos no elegibles permanecen sin cambios.',
    previewBulkAction: 'Revisar cambio de etapa',
    bulkPreview: 'Vista previa del cambio de etapa',
    bulkPreviewSummary:
      '{ready} pueden actualizarse y {blocked} necesitan revisión o ya están en la etapa objetivo.',
    confirmBulkTitle: 'Confirmar cambio de etapa',
    confirmBulkDescription: '¿Mover {count} candidatos elegibles a {stage}?',
    confirmBulkAction: 'Actualizar candidatos elegibles',
    bulkResult: 'Resultado del cambio de etapa',
    bulkResultSummary:
      '{succeeded} actualizados. {failed} necesitan revisión. {skipped} ya estaban al día.',
    failedItemsSelected:
      'Los candidatos que no pudieron actualizarse siguen seleccionados para que puedas revisarlos.',
    settings: 'Configuración de solicitudes',
    questions: 'Preguntas de solicitud',
    onboarding: 'Lista de incorporación',
    choosePlayer: 'Elegir Gobernador',
    privateNotes: 'Notas privadas de reclutamiento',
    stageHistory: 'Historial de etapas',
  },
} satisfies MessageCatalogue;

export default messages;
