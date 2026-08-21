import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'İttifak üye alımı',
    title: 'Üye alımı',
    candidates: 'Adaylar',
    accepted: 'Kabul edildi',
    joined: 'Katıldı',
    pipeline: 'Üye alımı',
    backToPipeline: 'Üye alımına dön',
    stage: 'Aşama',
    source: 'Kaynak',
    submitted: 'Gönderildi',
    nextAction: 'Sonraki işlem',
    bulkActions: 'Aday aşaması değişiklikleri',
    selectedCandidates: '{count} aday seçildi',
    bulkPreviewHelp: 'Değişikliği uygulamadan önce kimlerin taşınabileceğini kontrol et. Uygun olmayan adaylar değişmeden kalır.',
    previewBulkAction: 'Aşama değişikliğini kontrol et',
    bulkPreview: 'Aşama değişikliği önizlemesi',
    bulkPreviewSummary: '{ready} güncellenebilir; {blocked} incelenmeli veya zaten hedef aşamadadır.',
    confirmBulkTitle: 'Aşama değişikliğini onayla',
    confirmBulkDescription: 'Uygun {count} adayı {stage} aşamasına taşı?',
    confirmBulkAction: 'Uygun adayları güncelle',
    bulkResult: 'Aşama değişikliği sonucu',
    bulkResultSummary: '{succeeded} güncellendi. {failed} incelenmeli. {skipped} zaten günceldi.',
    failedItemsSelected: 'Güncellenemeyen adaylar incelemen için seçili kalır.',
    settings: 'Başvuru ayarları',
    questions: 'Başvuru soruları',
    onboarding: 'Katılım kontrol listesi',
    choosePlayer: 'Vali seç',
    privateNotes: 'Özel üye alımı notları',
    stageHistory: 'Aşama geçmişi',
  },
} satisfies MessageCatalogue;

export default messages;
