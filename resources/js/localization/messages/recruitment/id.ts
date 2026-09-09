import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    searchChoices: 'Cari pilihan',
    findChoices: 'Cari',
    noChoices: 'Tidak ada pilihan yang cocok.',
    choiceLookupFailed: 'Pilihan tidak dapat dimuat. Coba lagi.',
    activeConfigurationLimit:
      'Maksimal {limit} item aktif. Nonaktifkan satu item untuk menambahkan item lain.',
    historyItemsOnPage: '{count} catatan di halaman ini (maksimal {pageSize}).',
    bulkOutcome: {
      'permission-denied': 'Izin ditolak',
    },
    eyebrow: 'Rekrutmen aliansi',
    title: 'Rekrutmen',
    candidates: 'Kandidat',
    accepted: 'Diterima',
    joined: 'Bergabung',
    pipeline: 'Rekrutmen',
    backToPipeline: 'Kembali ke rekrutmen',
    stage: 'Tahap',
    source: 'Sumber',
    submitted: 'Dikirim',
    nextAction: 'Tindakan berikutnya',
    bulkActions: 'Perubahan tahap kandidat',
    selectedCandidates: '{count} kandidat dipilih',
    bulkPreviewHelp:
      'Tinjau kandidat yang dapat dipindahkan sebelum menerapkan perubahan. Kandidat yang tidak memenuhi syarat tetap tidak berubah.',
    previewBulkAction: 'Tinjau perubahan tahap',
    bulkPreview: 'Pratinjau perubahan tahap',
    bulkPreviewSummary:
      '{ready} dapat diperbarui dan {blocked} perlu ditinjau atau sudah berada di tahap tujuan.',
    confirmBulkTitle: 'Konfirmasi perubahan tahap',
    confirmBulkDescription: 'Pindahkan {count} kandidat yang memenuhi syarat ke {stage}?',
    confirmBulkAction: 'Perbarui kandidat yang memenuhi syarat',
    bulkResult: 'Hasil perubahan tahap',
    bulkResultSummary: '{succeeded} diperbarui. {failed} perlu ditinjau. {skipped} sudah terbaru.',
    failedItemsSelected: 'Kandidat yang tidak dapat diperbarui tetap dipilih agar dapat ditinjau.',
    settings: 'Pengaturan aplikasi',
    questions: 'Pertanyaan aplikasi',
    onboarding: 'Daftar onboarding',
    choosePlayer: 'Pilih Gubernur',
    privateNotes: 'Catatan privat perekrut',
    stageHistory: 'Riwayat tahap',
  },
} satisfies MessageCatalogue;

export default messages;
