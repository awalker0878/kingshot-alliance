import type { LocaleCode } from './locales';

export const governanceCatalogueLabels: Record<LocaleCode, Record<string, string>> = {
  ar: {
    choiceUnavailable: 'لم يعد الخيار المحدد متاحاً.',
    searchChoices: 'البحث في الخيارات',
    choicePageSummary:
      '{count} خيارات في هذه الصفحة؛ {total} نتيجة مطابقة. مرتبة حسب معرّف السجل الثابت.',
    choicesFailed: 'تعذّر تحميل الخيارات. لم يتغير اختيارك ولا الصفحة المعروضة.',
    retryChoices: 'إعادة تحميل الخيارات',
    recordCount: '{count} سجل',
    historyUnavailable: 'تعذر تحميل هذه الصفحة.',
    retryPage: 'إعادة تحميل الصفحة',
  },
  de: {
    choiceUnavailable: 'Die gewählte Option ist nicht mehr verfügbar.',
    searchChoices: 'Optionen suchen',
    choicePageSummary:
      '{count} Optionen auf dieser Seite; {total} Treffer. Nach stabiler Datensatz-ID sortiert.',
    choicesFailed:
      'Optionen konnten nicht geladen werden. Auswahl und angezeigte Seite bleiben unverändert.',
    retryChoices: 'Optionen erneut laden',
    recordCount: '{count} Einträge',
    historyUnavailable: 'Diese Seite konnte nicht geladen werden.',
    retryPage: 'Seite erneut laden',
  },
  en: {
    choiceUnavailable: 'Selected choice is no longer available.',
    searchChoices: 'Search choices',
    choicePageSummary: '{count} choices on this page; {total} match. Ordered by stable record ID.',
    choicesFailed:
      'Choices could not be loaded. Your selection and the displayed page have not changed.',
    retryChoices: 'Retry choices',
    recordCount: '{count} records',
    historyUnavailable: 'This page could not be loaded.',
    retryPage: 'Retry page',
  },
  es: {
    choiceUnavailable: 'La opción seleccionada ya no está disponible.',
    searchChoices: 'Buscar opciones',
    choicePageSummary:
      '{count} opciones en esta página; {total} coinciden. Ordenadas por identificador estable.',
    choicesFailed:
      'No se pudieron cargar las opciones. Se conservan la selección y la página mostrada.',
    retryChoices: 'Reintentar opciones',
    recordCount: '{count} registros',
    historyUnavailable: 'No se pudo cargar esta página.',
    retryPage: 'Reintentar página',
  },
  fr: {
    choiceUnavailable: 'L’option sélectionnée n’est plus disponible.',
    searchChoices: 'Rechercher des options',
    choicePageSummary:
      '{count} options sur cette page ; {total} correspondent. Ordre par identifiant stable.',
    choicesFailed:
      'Impossible de charger les options. Votre sélection et la page affichée sont conservées.',
    retryChoices: 'Réessayer',
    recordCount: '{count} enregistrements',
    historyUnavailable: 'Impossible de charger cette page.',
    retryPage: 'Réessayer la page',
  },
  id: {
    choiceUnavailable: 'Pilihan yang dipilih tidak lagi tersedia.',
    searchChoices: 'Cari pilihan',
    choicePageSummary:
      '{count} pilihan pada halaman ini; {total} cocok. Diurutkan menurut ID rekaman tetap.',
    choicesFailed:
      'Pilihan tidak dapat dimuat. Pilihan Anda dan halaman yang tampil tidak berubah.',
    retryChoices: 'Coba lagi',
    recordCount: '{count} catatan',
    historyUnavailable: 'Halaman ini tidak dapat dimuat.',
    retryPage: 'Coba lagi halaman',
  },
  it: {
    choiceUnavailable: 'L’opzione selezionata non è più disponibile.',
    searchChoices: 'Cerca opzioni',
    choicePageSummary:
      '{count} opzioni in questa pagina; {total} corrispondenze. Ordine per ID stabile.',
    choicesFailed:
      'Impossibile caricare le opzioni. Selezione e pagina visualizzata restano invariate.',
    retryChoices: 'Riprova opzioni',
    recordCount: '{count} record',
    historyUnavailable: 'Impossibile caricare questa pagina.',
    retryPage: 'Riprova pagina',
  },
  ja: {
    choiceUnavailable: '選択した項目は利用できなくなりました。',
    searchChoices: '選択肢を検索',
    choicePageSummary:
      'このページは{count}件、該当する選択肢は全{total}件です。固定レコードID順です。',
    choicesFailed: '選択肢を読み込めませんでした。選択内容と表示ページは変更されていません。',
    retryChoices: '再読み込み',
    recordCount: '{count} 件の記録',
    historyUnavailable: 'このページを読み込めませんでした。',
    retryPage: 'ページを再読み込み',
  },
  ko: {
    choiceUnavailable: '선택한 항목을 더 이상 사용할 수 없습니다.',
    searchChoices: '선택 항목 검색',
    choicePageSummary: '이 페이지에 {count}개, 일치하는 항목 총 {total}개. 고정 레코드 ID순입니다.',
    choicesFailed: '선택 항목을 불러오지 못했습니다. 선택과 표시된 페이지는 유지됩니다.',
    retryChoices: '다시 불러오기',
    recordCount: '기록 {count}개',
    historyUnavailable: '이 페이지를 불러올 수 없습니다.',
    retryPage: '페이지 다시 시도',
  },
  pl: {
    choiceUnavailable: 'Wybrana opcja nie jest już dostępna.',
    searchChoices: 'Szukaj opcji',
    choicePageSummary:
      '{count} opcji na tej stronie; {total} pasuje. Kolejność według stałego identyfikatora.',
    choicesFailed: 'Nie udało się wczytać opcji. Wybór i wyświetlana strona pozostają bez zmian.',
    retryChoices: 'Ponów wczytanie',
    recordCount: '{count} rekordów',
    historyUnavailable: 'Nie udało się wczytać tej strony.',
    retryPage: 'Ponów wczytanie strony',
  },
  'pt-BR': {
    choiceUnavailable: 'A opção selecionada não está mais disponível.',
    searchChoices: 'Buscar opções',
    choicePageSummary:
      '{count} opções nesta página; {total} correspondem. Ordenadas por identificador estável.',
    choicesFailed:
      'Não foi possível carregar as opções. A seleção e a página exibida foram preservadas.',
    retryChoices: 'Tentar novamente',
    recordCount: '{count} registros',
    historyUnavailable: 'Não foi possível carregar esta página.',
    retryPage: 'Tentar a página novamente',
  },
  ru: {
    choiceUnavailable: 'Выбранный вариант больше недоступен.',
    searchChoices: 'Поиск вариантов',
    choicePageSummary:
      '{count} вариантов на странице; найдено {total}. Порядок по постоянному идентификатору.',
    choicesFailed: 'Не удалось загрузить варианты. Выбор и текущая страница сохранены.',
    retryChoices: 'Повторить загрузку',
    recordCount: 'Записей: {count}',
    historyUnavailable: 'Не удалось загрузить эту страницу.',
    retryPage: 'Повторить загрузку',
  },
  th: {
    choiceUnavailable: 'ตัวเลือกที่เลือกไม่พร้อมใช้งานแล้ว',
    searchChoices: 'ค้นหาตัวเลือก',
    choicePageSummary:
      'หน้านี้มี {count} ตัวเลือก ตรงกันทั้งหมด {total} รายการ เรียงตามรหัสระเบียนคงที่',
    choicesFailed: 'โหลดตัวเลือกไม่สำเร็จ ตัวเลือกที่เลือกและหน้าที่แสดงยังคงเดิม',
    retryChoices: 'ลองโหลดอีกครั้ง',
    recordCount: '{count} รายการ',
    historyUnavailable: 'ไม่สามารถโหลดหน้านี้ได้',
    retryPage: 'ลองโหลดหน้าอีกครั้ง',
  },
  tr: {
    choiceUnavailable: 'Seçilen seçenek artık kullanılamıyor.',
    searchChoices: 'Seçenek ara',
    choicePageSummary:
      'Bu sayfada {count} seçenek; toplam {total} eşleşme. Sabit kayıt kimliğine göre sıralanır.',
    choicesFailed: 'Seçenekler yüklenemedi. Seçiminiz ve görüntülenen sayfa değişmedi.',
    retryChoices: 'Yeniden dene',
    recordCount: '{count} kayıt',
    historyUnavailable: 'Bu sayfa yüklenemedi.',
    retryPage: 'Sayfayı yeniden dene',
  },
  vi: {
    choiceUnavailable: 'Lựa chọn đã chọn không còn khả dụng.',
    searchChoices: 'Tìm lựa chọn',
    choicePageSummary:
      '{count} lựa chọn trên trang; {total} kết quả phù hợp. Sắp xếp theo mã bản ghi ổn định.',
    choicesFailed: 'Không thể tải lựa chọn. Lựa chọn của bạn và trang đang xem được giữ nguyên.',
    retryChoices: 'Thử tải lại',
    recordCount: '{count} bản ghi',
    historyUnavailable: 'Không thể tải trang này.',
    retryPage: 'Thử tải lại trang',
  },
  'zh-CN': {
    choiceUnavailable: '所选选项已不可用。',
    searchChoices: '搜索选项',
    choicePageSummary: '本页 {count} 个选项，共 {total} 个匹配项。按固定记录标识排序。',
    choicesFailed: '无法加载选项。当前选择和显示的页面保持不变。',
    retryChoices: '重试加载',
    recordCount: '{count} 条记录',
    historyUnavailable: '无法加载此页面。',
    retryPage: '重试此页',
  },
  'zh-TW': {
    choiceUnavailable: '所選項目已無法使用。',
    searchChoices: '搜尋選項',
    choicePageSummary: '本頁 {count} 個選項，共 {total} 個相符項目。依固定記錄識別碼排序。',
    choicesFailed: '無法載入選項。目前選擇與顯示頁面保持不變。',
    retryChoices: '重新載入',
    recordCount: '{count} 筆記錄',
    historyUnavailable: '無法載入此頁面。',
    retryPage: '重試此頁',
  },
};
