import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} catatan',
    historyUnavailable: 'Halaman ini tidak dapat dimuat.',
    retryPage: 'Coba lagi halaman',
    title: 'Administrasi platform',
    backDashboard: 'Kembali ke Beranda',
    localizationRuntime: 'Bahasa',
  },
} satisfies MessageCatalogue;

export default messages;
