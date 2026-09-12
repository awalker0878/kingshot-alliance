import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} kayıt',
    historyUnavailable: 'Bu sayfa yüklenemedi.',
    retryPage: 'Sayfayı yeniden dene',
    title: 'Platform yönetimi',
    backDashboard: 'Ana sayfaya dön',
    localizationRuntime: 'Diller',
  },
} satisfies MessageCatalogue;

export default messages;
