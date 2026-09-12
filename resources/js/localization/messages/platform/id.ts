import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Pilihan yang dipilih tidak lagi tersedia.',
    searchChoices: 'Cari pilihan',
    choicePageSummary:
      '{count} pilihan pada halaman ini; {total} cocok. Diurutkan menurut ID rekaman tetap.',
    choicesFailed:
      'Pilihan tidak dapat dimuat. Pilihan Anda dan halaman yang tampil tidak berubah.',
    retryChoices: 'Coba lagi',
    recoveryFailed: 'Pemulihan tidak dapat diselesaikan. Silakan coba lagi.',
    recordCount: '{count} catatan',
    historyUnavailable: 'Halaman ini tidak dapat dimuat.',
    retryPage: 'Coba lagi halaman',
    title: 'Administrasi platform',
    backDashboard: 'Kembali ke Beranda',
    localizationRuntime: 'Bahasa',
  },
} satisfies MessageCatalogue;

export default messages;
