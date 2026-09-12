import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Seçilen seçenek artık kullanılamıyor.',
    searchChoices: 'Seçenek ara',
    choicePageSummary:
      'Bu sayfada {count} seçenek; toplam {total} eşleşme. Sabit kayıt kimliğine göre sıralanır.',
    choicesFailed: 'Seçenekler yüklenemedi. Seçiminiz ve görüntülenen sayfa değişmedi.',
    retryChoices: 'Yeniden dene',
    recoveryFailed: 'Kurtarma tamamlanamadı. Lütfen yeniden deneyin.',
    recordCount: '{count} kayıt',
    historyUnavailable: 'Bu sayfa yüklenemedi.',
    retryPage: 'Sayfayı yeniden dene',
    title: 'Platform yönetimi',
    backDashboard: 'Ana sayfaya dön',
    localizationRuntime: 'Diller',
  },
} satisfies MessageCatalogue;

export default messages;
