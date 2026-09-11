import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: 'Bu sayfada {count} kayıt · toplam {total}',
    collectionFailed: 'Liste yüklenemedi. İlk sayfadan yeniden deneyin.',
    selectedUnavailable: 'Seçili öğe kullanılamıyor',
    reviewOnThisPage: 'Bu inceleme listesi geçerli katalog sayfasını kapsar.',
    allStatuses: 'Tüm durumlar',
    retryCandidateSummary:
      'Deneme sınırının altındaki {total} başarısızlıktan {selected} tanesi yeniden denemek için gösteriliyor.',
    eyebrow: 'İttifak içeriği',
    hubTitle: 'İçerik merkezi',
    manageContent: 'İçeriği yönet',
    results: 'Sonuçlar',
    publicItems: 'Herkese açık',
    memberItems: 'Yalnızca üyeler',
    categories: 'Kategoriler',
    search: 'İttifak içeriğinde ara',
    type: 'İçerik türü',
    category: 'Kategori',
    locale: 'Dil',
    applyFilters: 'Filtreleri uygula',
    clear: 'Temizle',
    publishedContent: 'Yayınlanmış içerik',
    publicProfile: 'Herkese açık ittifak profili',
    createContent: 'İçerik oluştur',
    editContent: 'İçeriği düzenle',
    mediaLibrary: 'Medya kitaplığı',
    contentInventory: 'İçerik envanteri',
  },
} satisfies MessageCatalogue;

export default messages;
