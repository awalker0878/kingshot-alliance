import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Recrutamento da aliança',
    title: 'Recrutamento',
    candidates: 'Candidatos',
    accepted: 'Aceitos',
    joined: 'Entraram',
    pipeline: 'Recrutamento',
    backToPipeline: 'Voltar ao recrutamento',
    stage: 'Etapa',
    source: 'Origem',
    submitted: 'Enviado',
    nextAction: 'Próxima ação',
    bulkActions: 'Mudanças de etapa dos candidatos',
    selectedCandidates: '{count} candidatos selecionados',
    bulkPreviewHelp: 'Revise quem pode mudar de etapa antes de aplicar a alteração. Candidatos não elegíveis permanecem sem mudanças.',
    previewBulkAction: 'Revisar mudança de etapa',
    bulkPreview: 'Prévia da mudança de etapa',
    bulkPreviewSummary: '{ready} podem ser atualizados e {blocked} precisam de revisão ou já estão na etapa de destino.',
    confirmBulkTitle: 'Confirmar mudança de etapa',
    confirmBulkDescription: 'Mover {count} candidatos elegíveis para {stage}?',
    confirmBulkAction: 'Atualizar candidatos elegíveis',
    bulkResult: 'Resultado da mudança de etapa',
    bulkResultSummary: '{succeeded} atualizados. {failed} precisam de revisão. {skipped} já estavam atualizados.',
    failedItemsSelected: 'Os candidatos que não puderam ser atualizados permanecem selecionados para revisão.',
    settings: 'Configurações de inscrição',
    questions: 'Perguntas da inscrição',
    onboarding: 'Checklist de integração',
    choosePlayer: 'Selecionar Governador',
    privateNotes: 'Notas privadas do recrutador',
    stageHistory: 'Histórico de etapas',
  },
} satisfies MessageCatalogue;

export default messages;
