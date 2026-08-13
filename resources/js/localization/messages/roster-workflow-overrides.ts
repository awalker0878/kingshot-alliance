import type { LocaleCode } from '../locales';
import type { RosterWorkflowMessageTree } from './roster-workflows';

type OverrideMap = Partial<Record<LocaleCode, RosterWorkflowMessageTree>>;

export const rosterWorkflowOverrides: OverrideMap = {
  pl: {
    rosterImport: {
      title: 'Kontrolowana migracja CSV',
      subtitle:
        'Wyświetl podgląd każdego wiersza przed zapisem. Tylko stabilne identyfikatory gry są dopasowywane automatycznie; dopasowanie po nazwie wymaga wyraźnej decyzji.',
      exportCurrent: 'Eksportuj bieżący skład',
      exportManager: 'Eksportuj z polami menedżera',
      uploadPreview: 'Prześlij do podglądu próbnego',
      schemaHelp:
        'Schemat {version} · maksymalnie {rows} wierszy danych · maksymalnie {bytes}. CSV UTF-8 jest analizowany wyłącznie jako tekst.',
      csvFile: 'Plik CSV',
      validatePreview: 'Sprawdź i pokaż podgląd',
      requiredColumns: 'Wymagane kolumny',
      requirementsHelp:
        'name, power i state są wymagane. state to active, tracked lub left. joined_at używa YYYY-MM-DD; opcjonalne captured_at używa ISO-8601 ze strefą czasową. Powtórzone stabilne identyfikatory gry są odrzucane.',
      preview: 'Podgląd: {filename}',
      rows: 'Wiersze',
      creates: 'Nowe',
      updates: 'Aktualizacje',
      ambiguous: 'Niejednoznaczne',
      rejected: 'Odrzucone',
      committedSummary:
        'Zatwierdzono {rows} wierszy: {creates} nowych profili, {updates} aktualizacji i {snapshots} nowych migawek dopisanych do historii.',
      csvRow: 'Wiersz CSV',
      previewOutcome: 'Wynik podglądu',
      resolutionErrors: 'Rozstrzygnięcie / błędy',
      gameIdNotSupplied: 'Nie podano ID gry',
      chooseResolution: 'Wybierz rozstrzygnięcie',
      createNewIdentity: 'Utwórz nową tożsamość gracza',
      updateCandidate: 'Aktualizuj {name} · {gameId} · {state}',
      stableMatch: 'Stabilne ID gry pasuje do wpisu składu {entry}.',
      newIdentity: 'Nowa tożsamość składu.',
      rejectedBlock:
        'Nie można zatwierdzić partii, dopóki jakikolwiek wiersz jest odrzucony. Popraw CSV i prześlij go ponownie.',
      unresolvedRows: 'Rozstrzygnij {count} niejednoznacznych wierszy przed potwierdzeniem.',
      confirmAtomic: 'Potwierdź atomowy import',
      commitError:
        'Nie udało się zatwierdzić importu. Sprawdź rozstrzygnięcia wierszy lub utwórz nowy podgląd.',
    },
    rosterHistory: {
      title: 'Historia migawek',
      currentHelp:
        'Aktualna oznacza, że najnowsza migawka ma nie więcej niż {days} dni. Brak oznacza, że nie zapisano migawki. Wiersze historyczne pozostają niezmiennymi obserwacjami.',
      recordSnapshot: 'Zapisz migawkę',
      recordHelp:
        'Zapisz stan zaobserwowany w chwili przechwycenia. Ponowienie tej samej zaakceptowanej obserwacji jest idempotentne; późniejszy czas tworzy nowy wiersz historii.',
      observedPlayerName: 'Zaobserwowana nazwa gracza',
      capturedAt: 'Czas przechwycenia',
      recordAction: 'Zapisz migawkę',
      historyHeading: 'Historia migawek',
      historyHelp: 'Najnowsze przechwycenie najpierw. Wyświetlanych jest do 250 ostatnich obserwacji.',
      source: 'Źródło',
      recordedBy: 'Zapisane przez',
      noSnapshots: 'Nie zapisano migawek dla tego gracza.',
    },
  },
  ru: {
    rosterImport: {
      title: 'Контролируемый импорт CSV',
      subtitle:
        'Просматривайте каждую строку до сохранения. Автоматически сопоставляются только стабильные игровые ID; совпадение по имени требует явного решения.',
      exportCurrent: 'Экспортировать текущий состав',
      exportManager: 'Экспортировать с полями руководителя',
      uploadPreview: 'Загрузить для пробного просмотра',
      schemaHelp:
        'Схема {version} · максимум {rows} строк данных · максимум {bytes}. CSV в UTF-8 обрабатывается только как текст.',
      csvFile: 'Файл CSV',
      validatePreview: 'Проверить и показать',
      requiredColumns: 'Обязательные столбцы',
      requirementsHelp:
        'name, power и state обязательны. state: active, tracked или left. joined_at использует YYYY-MM-DD; необязательный captured_at — ISO-8601 с часовым поясом. Повторяющиеся стабильные игровые ID отклоняются.',
      preview: 'Предпросмотр: {filename}',
      rows: 'Строки',
      creates: 'Создания',
      updates: 'Обновления',
      ambiguous: 'Неоднозначные',
      rejected: 'Отклонённые',
      committedSummary:
        'Подтверждено {rows} строк: {creates} новых профилей, {updates} обновлений и {snapshots} новых снимков, добавленных в историю.',
      csvRow: 'Строка CSV',
      previewOutcome: 'Результат проверки',
      resolutionErrors: 'Решение / ошибки',
      gameIdNotSupplied: 'Игровой ID не указан',
      chooseResolution: 'Выберите решение',
      createNewIdentity: 'Создать новую игровую личность',
      updateCandidate: 'Обновить {name} · {gameId} · {state}',
      stableMatch: 'Стабильный игровой ID совпадает с записью состава {entry}.',
      newIdentity: 'Новая личность состава.',
      rejectedBlock:
        'Пакет нельзя подтвердить, пока есть отклонённая строка. Исправьте CSV и загрузите снова.',
      unresolvedRows: 'Разрешите {count} неоднозначных строк перед подтверждением.',
      confirmAtomic: 'Подтвердить атомарный импорт',
      commitError:
        'Не удалось подтвердить импорт. Проверьте решения для строк или создайте новый предпросмотр.',
    },
    rosterHistory: {
      title: 'История снимков',
      currentHelp:
        'Актуальный означает, что последнему снимку не больше {days} дней. Отсутствует означает, что снимков нет. Исторические строки остаются неизменяемыми наблюдениями.',
      recordSnapshot: 'Записать снимок',
      recordHelp:
        'Запишите наблюдение на момент фиксации. Повтор той же принятой записи идемпотентен; более позднее время создаёт новую историческую строку.',
      observedPlayerName: 'Наблюдаемое имя игрока',
      capturedAt: 'Время фиксации',
      recordAction: 'Записать снимок',
      historyHeading: 'История снимков',
      historyHelp: 'Сначала самые новые. Показываются до 250 последних наблюдений.',
      source: 'Источник',
      recordedBy: 'Записал',
      noSnapshots: 'Для этого игрока снимки не записаны.',
    },
  },
  th: {
    rosterImport: {
      title: 'การย้าย CSV แบบควบคุม',
      subtitle:
        'ตรวจดูทุกแถวก่อนบันทึก ระบบจับคู่อัตโนมัติเฉพาะ ID เกมแบบคงที่ ส่วนชื่อที่ตรงกันต้องตัดสินใจอย่างชัดเจน',
      exportCurrent: 'ส่งออกรายชื่อปัจจุบัน',
      exportManager: 'ส่งออกพร้อมช่องผู้จัดการ',
      uploadPreview: 'อัปโหลดเพื่อดูตัวอย่าง',
      schemaHelp:
        'สคีมา {version} · สูงสุด {rows} แถวข้อมูล · สูงสุด {bytes} ระบบอ่าน CSV UTF-8 เป็นข้อความเท่านั้น',
      csvFile: 'ไฟล์ CSV',
      validatePreview: 'ตรวจสอบและดูตัวอย่าง',
      requiredColumns: 'คอลัมน์ที่ต้องมี',
      requirementsHelp:
        'ต้องมี name, power และ state โดย state เป็น active, tracked หรือ left; joined_at ใช้ YYYY-MM-DD และ captured_at ถ้ามีต้องเป็น ISO-8601 พร้อมเขตเวลา ID เกมแบบคงที่ที่ซ้ำกันจะถูกปฏิเสธ',
      preview: 'ตัวอย่าง: {filename}',
      rows: 'แถว',
      creates: 'สร้างใหม่',
      updates: 'อัปเดต',
      ambiguous: 'กำกวม',
      rejected: 'ถูกปฏิเสธ',
      committedSummary:
        'ยืนยัน {rows} แถว: สร้างรายชื่อ {creates}, อัปเดต {updates} และเพิ่มสแนปช็อตแบบต่อท้าย {snapshots}',
      csvRow: 'แถว CSV',
      previewOutcome: 'ผลตัวอย่าง',
      resolutionErrors: 'การแก้ไข / ข้อผิดพลาด',
      gameIdNotSupplied: 'ไม่ได้ระบุ ID เกม',
      chooseResolution: 'เลือกวิธีแก้ไข',
      createNewIdentity: 'สร้างตัวตนผู้เล่นใหม่',
      updateCandidate: 'อัปเดต {name} · {gameId} · {state}',
      stableMatch: 'ID เกมแบบคงที่ตรงกับรายการ {entry}',
      newIdentity: 'ตัวตนรายชื่อใหม่',
      rejectedBlock: 'ไม่สามารถยืนยันชุดนี้ได้ขณะที่มีแถวถูกปฏิเสธ โปรดแก้ CSV แล้วอัปโหลดใหม่',
      unresolvedRows: 'แก้ไขแถวกำกวม {count} แถวก่อนยืนยัน',
      confirmAtomic: 'ยืนยันการนำเข้าแบบอะตอมิก',
      commitError: 'ไม่สามารถยืนยันการนำเข้าได้ โปรดตรวจการแก้ไขแต่ละแถวหรือสร้างตัวอย่างใหม่',
    },
    rosterHistory: {
      title: 'ประวัติสแนปช็อต',
      currentHelp:
        'ปัจจุบันหมายถึงสแนปช็อตล่าสุดมีอายุไม่เกิน {days} วัน ขาดหายหมายถึงยังไม่มีสแนปช็อต แถวประวัติยังคงเป็นข้อมูลสังเกตที่แก้ไขไม่ได้',
      recordSnapshot: 'บันทึกสแนปช็อต',
      recordHelp:
        'บันทึกสิ่งที่สังเกต ณ เวลาที่จับข้อมูล การส่งข้อมูลเดิมซ้ำไม่สร้างรายการซ้ำ ส่วนเวลาที่ใหม่กว่าจะสร้างแถวประวัติใหม่',
      observedPlayerName: 'ชื่อผู้เล่นที่สังเกต',
      capturedAt: 'เวลาที่จับข้อมูล',
      recordAction: 'บันทึกสแนปช็อต',
      historyHeading: 'ประวัติสแนปช็อต',
      historyHelp: 'แสดงรายการล่าสุดก่อน สูงสุด 250 การสังเกตล่าสุด',
      source: 'แหล่งที่มา',
      recordedBy: 'บันทึกโดย',
      noSnapshots: 'ยังไม่มีสแนปช็อตสำหรับผู้เล่นนี้',
    },
  },
  tr: {
    rosterImport: {
      title: 'Kontrollü CSV geçişi',
      subtitle:
        'Kaydetmeden önce her satırı önizleyin. Yalnızca sabit oyun kimlikleri otomatik eşleşir; ad eşleşmeleri açık bir karar gerektirir.',
      exportCurrent: 'Mevcut kadroyu dışa aktar',
      exportManager: 'Yönetici alanlarıyla dışa aktar',
      uploadPreview: 'Önizleme için yükle',
      schemaHelp:
        'Şema {version} · en fazla {rows} veri satırı · en fazla {bytes}. UTF-8 CSV yalnızca metin olarak ayrıştırılır.',
      csvFile: 'CSV dosyası',
      validatePreview: 'Doğrula ve önizle',
      requiredColumns: 'Gerekli sütunlar',
      requirementsHelp:
        'name, power ve state gereklidir. state active, tracked veya left olur. joined_at YYYY-MM-DD kullanır; captured_at verilirse saat dilimli ISO-8601 olmalıdır. Yinelenen sabit oyun kimlikleri reddedilir.',
      preview: 'Önizleme: {filename}',
      rows: 'Satırlar',
      creates: 'Oluşturma',
      updates: 'Güncelleme',
      ambiguous: 'Belirsiz',
      rejected: 'Reddedilen',
      committedSummary:
        '{rows} satır onaylandı: {creates} kadro oluşturma, {updates} güncelleme ve {snapshots} yeni eklemeli anlık görüntü.',
      csvRow: 'CSV satırı',
      previewOutcome: 'Önizleme sonucu',
      resolutionErrors: 'Çözüm / hatalar',
      gameIdNotSupplied: 'Oyun kimliği verilmedi',
      chooseResolution: 'Çözüm seç',
      createNewIdentity: 'Yeni oyun oyuncusu kimliği oluştur',
      updateCandidate: '{name} güncelle · {gameId} · {state}',
      stableMatch: 'Sabit oyun kimliği {entry} kadro girdisiyle eşleşiyor.',
      newIdentity: 'Yeni kadro kimliği.',
      rejectedBlock:
        'Reddedilen bir satır varken bu toplu işlem onaylanamaz. CSV dosyasını düzeltip yeniden yükleyin.',
      unresolvedRows: 'Onaydan önce {count} belirsiz satırı çözün.',
      confirmAtomic: 'Atomik içe aktarmayı onayla',
      commitError: 'İçe aktarma onaylanamadı. Satır çözümlerini inceleyin veya yeni önizleme oluşturun.',
    },
    rosterHistory: {
      title: 'Anlık görüntü geçmişi',
      currentHelp:
        'Güncel, son kaydın en fazla {days} günlük olduğu anlamına gelir. Eksik, hiç kayıt olmadığı anlamına gelir. Geçmiş satırlar değiştirilemez gözlemler olarak kalır.',
      recordSnapshot: 'Anlık görüntü kaydet',
      recordHelp:
        'Yakalama anında gözlenen bilgiyi kaydedin. Aynı kabul edilmiş gözlemi tekrar göndermek idempotenttir; daha sonraki zaman yeni bir geçmiş satırı oluşturur.',
      observedPlayerName: 'Gözlenen oyuncu adı',
      capturedAt: 'Yakalama zamanı',
      recordAction: 'Anlık görüntü kaydet',
      historyHeading: 'Anlık görüntü geçmişi',
      historyHelp: 'En yeni kayıt önce gösterilir. En fazla son 250 gözlem görüntülenir.',
      source: 'Kaynak',
      recordedBy: 'Kaydeden',
      noSnapshots: 'Bu oyuncu için anlık görüntü kaydı yok.',
    },
  },
  vi: {
    rosterImport: {
      title: 'Di chuyển CSV có kiểm soát',
      subtitle:
        'Xem trước từng dòng trước khi lưu. Chỉ ID game ổn định được khớp tự động; khớp theo tên cần quyết định rõ ràng.',
      exportCurrent: 'Xuất roster hiện tại',
      exportManager: 'Xuất kèm trường quản lý',
      uploadPreview: 'Tải lên để xem trước',
      schemaHelp:
        'Lược đồ {version} · tối đa {rows} dòng dữ liệu · tối đa {bytes}. CSV UTF-8 chỉ được phân tích dưới dạng văn bản.',
      csvFile: 'Tệp CSV',
      validatePreview: 'Xác thực và xem trước',
      requiredColumns: 'Cột bắt buộc',
      requirementsHelp:
        'name, power và state là bắt buộc. state là active, tracked hoặc left. joined_at dùng YYYY-MM-DD; captured_at nếu có dùng ISO-8601 kèm múi giờ. ID game ổn định bị lặp sẽ bị từ chối.',
      preview: 'Xem trước: {filename}',
      rows: 'Dòng',
      creates: 'Tạo mới',
      updates: 'Cập nhật',
      ambiguous: 'Mơ hồ',
      rejected: 'Bị từ chối',
      committedSummary:
        'Đã xác nhận {rows} dòng: {creates} hồ sơ mới, {updates} cập nhật và {snapshots} snapshot mới chỉ thêm vào lịch sử.',
      csvRow: 'Dòng CSV',
      previewOutcome: 'Kết quả xem trước',
      resolutionErrors: 'Xử lý / lỗi',
      gameIdNotSupplied: 'Chưa cung cấp ID game',
      chooseResolution: 'Chọn cách xử lý',
      createNewIdentity: 'Tạo danh tính người chơi mới',
      updateCandidate: 'Cập nhật {name} · {gameId} · {state}',
      stableMatch: 'ID game ổn định khớp với mục roster {entry}.',
      newIdentity: 'Danh tính roster mới.',
      rejectedBlock:
        'Không thể xác nhận lô khi còn dòng bị từ chối. Hãy sửa CSV và tải lên lại.',
      unresolvedRows: 'Xử lý {count} dòng mơ hồ trước khi xác nhận.',
      confirmAtomic: 'Xác nhận nhập nguyên tử',
      commitError: 'Không thể xác nhận nhập. Hãy xem lại xử lý từng dòng hoặc tạo bản xem trước mới.',
    },
    rosterHistory: {
      title: 'Lịch sử snapshot',
      currentHelp:
        'Hiện tại nghĩa là snapshot mới nhất không quá {days} ngày. Thiếu nghĩa là chưa có snapshot. Các dòng lịch sử vẫn là quan sát bất biến.',
      recordSnapshot: 'Ghi snapshot',
      recordHelp:
        'Ghi lại điều được quan sát tại thời điểm chụp. Gửi lại cùng một quan sát đã chấp nhận là idempotent; thời điểm muộn hơn tạo dòng lịch sử mới.',
      observedPlayerName: 'Tên người chơi quan sát',
      capturedAt: 'Thời điểm chụp',
      recordAction: 'Ghi snapshot',
      historyHeading: 'Lịch sử snapshot',
      historyHelp: 'Bản chụp mới nhất trước. Hiển thị tối đa 250 quan sát gần nhất.',
      source: 'Nguồn',
      recordedBy: 'Ghi bởi',
      noSnapshots: 'Chưa có snapshot nào được ghi cho người chơi này.',
    },
  },
};
