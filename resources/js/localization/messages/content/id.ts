import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} catatan di halaman ini · total {total}',
    collectionFailed: 'Daftar tidak dapat dimuat. Coba lagi dari halaman pertama.',
    selectedUnavailable: 'Item terpilih tidak tersedia',
    reviewOnThisPage: 'Daftar tinjauan ini mencakup halaman katalog saat ini.',
    allStatuses: 'Semua status',
    retryCandidateSummary:
      '{selected} dari {total} kegagalan di bawah batas percobaan ditampilkan untuk dicoba ulang.',
    eyebrow: 'Konten aliansi',
    hubTitle: 'Pusat konten',
    manageContent: 'Kelola konten',
    results: 'Hasil',
    publicItems: 'Publik',
    memberItems: 'Khusus anggota',
    categories: 'Kategori',
    search: 'Cari konten aliansi',
    type: 'Jenis konten',
    category: 'Kategori',
    locale: 'Bahasa',
    applyFilters: 'Terapkan filter',
    clear: 'Hapus',
    publishedContent: 'Konten diterbitkan',
    publicProfile: 'Profil publik aliansi',
    createContent: 'Buat konten',
    editContent: 'Edit konten',
    mediaLibrary: 'Pustaka media',
    contentInventory: 'Inventaris konten',
  },
} satisfies MessageCatalogue;

export default messages;
