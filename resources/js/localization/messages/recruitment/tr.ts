import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'İttifak işe alımı',
    title: 'İşe alım',
    candidates: 'Adaylar',
    accepted: 'Kabul edildi',
    joined: 'Katıldı',
    pipeline: 'Aday süreci',
    stage: 'Aşama',
    source: 'Kaynak',
    submitted: 'Gönderildi',
    nextAction: 'Sonraki işlem',
    settings: 'Başvuru ayarları',
    questions: 'Başvuru soruları',
    onboarding: 'Katılım kontrol listesi',
    privateNotes: 'Özel işe alım notları',
    stageHistory: 'Aşama geçmişi',
  },
} satisfies MessageCatalogue;

export default messages;
