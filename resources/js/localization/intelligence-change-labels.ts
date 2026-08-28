import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

type Labels = {
  title: string;
  subtitle: string;
  empty: string;
  source: string;
  observed: string;
  baseline: string;
  current: string;
  previous: string;
  viewSource: string;
  material: string;
  attention: string;
};

const labels: Record<LocaleCode, Labels> = {
  en: { title: 'Recent intelligence changes', subtitle: 'Source-cited changes derived from authorized history. Changes describe observations, not strategic intent.', empty: 'No material intelligence changes are available for this scope.', source: 'Source', observed: 'Observed', baseline: 'Baseline', current: 'Current', previous: 'Previous', viewSource: 'View source', material: 'Material change', attention: 'Needs verification' },
  ar: { title: 'أحدث تغييرات الاستخبارات', subtitle: 'تغييرات موثقة بالمصادر مشتقة من السجل المصرح به. تصف التغييرات الملاحظات ولا تستنتج النية الاستراتيجية.', empty: 'لا توجد تغييرات استخباراتية جوهرية متاحة لهذا النطاق.', source: 'المصدر', observed: 'لوحظ', baseline: 'خط الأساس', current: 'الحالي', previous: 'السابق', viewSource: 'عرض المصدر', material: 'تغيير جوهري', attention: 'يحتاج إلى تحقق' },
  de: { title: 'Aktuelle Intelligence-Änderungen', subtitle: 'Quellenbelegte Änderungen aus autorisierter Historie. Sie beschreiben Beobachtungen, keine strategische Absicht.', empty: 'Für diesen Bereich sind keine wesentlichen Intelligence-Änderungen verfügbar.', source: 'Quelle', observed: 'Beobachtet', baseline: 'Basis', current: 'Aktuell', previous: 'Vorher', viewSource: 'Quelle ansehen', material: 'Wesentliche Änderung', attention: 'Prüfung erforderlich' },
  es: { title: 'Cambios recientes de inteligencia', subtitle: 'Cambios citados con fuente derivados del historial autorizado. Describen observaciones, no intención estratégica.', empty: 'No hay cambios materiales de inteligencia disponibles para este ámbito.', source: 'Fuente', observed: 'Observado', baseline: 'Referencia', current: 'Actual', previous: 'Anterior', viewSource: 'Ver fuente', material: 'Cambio material', attention: 'Requiere verificación' },
  fr: { title: 'Changements récents du renseignement', subtitle: 'Changements sourcés dérivés de l’historique autorisé. Ils décrivent des observations, pas une intention stratégique.', empty: 'Aucun changement matériel de renseignement n’est disponible pour cette portée.', source: 'Source', observed: 'Observé', baseline: 'Référence', current: 'Actuel', previous: 'Précédent', viewSource: 'Voir la source', material: 'Changement matériel', attention: 'À vérifier' },
  id: { title: 'Perubahan intelijen terbaru', subtitle: 'Perubahan bersumber dari riwayat yang diizinkan. Perubahan menjelaskan observasi, bukan niat strategis.', empty: 'Tidak ada perubahan intelijen material untuk cakupan ini.', source: 'Sumber', observed: 'Diamati', baseline: 'Dasar', current: 'Saat ini', previous: 'Sebelumnya', viewSource: 'Lihat sumber', material: 'Perubahan material', attention: 'Perlu verifikasi' },
  it: { title: 'Modifiche recenti dell’intelligence', subtitle: 'Modifiche con fonte derivate dalla cronologia autorizzata. Descrivono osservazioni, non intenzioni strategiche.', empty: 'Nessuna modifica materiale dell’intelligence è disponibile per questo ambito.', source: 'Fonte', observed: 'Osservato', baseline: 'Riferimento', current: 'Attuale', previous: 'Precedente', viewSource: 'Vedi fonte', material: 'Modifica materiale', attention: 'Da verificare' },
  ja: { title: '最近のインテリジェンス変更', subtitle: '認可された履歴から導出した出典付きの変更です。観測事実を示し、戦略的意図は推測しません。', empty: 'この範囲に重要なインテリジェンス変更はありません。', source: '出典', observed: '観測', baseline: '基準', current: '現在', previous: '以前', viewSource: '出典を見る', material: '重要な変更', attention: '確認が必要' },
  ko: { title: '최근 정보 변경', subtitle: '권한이 있는 기록에서 도출된 출처 인용 변경입니다. 관측을 설명하며 전략적 의도를 추론하지 않습니다.', empty: '이 범위에 중요한 정보 변경이 없습니다.', source: '출처', observed: '관측', baseline: '기준', current: '현재', previous: '이전', viewSource: '출처 보기', material: '중요 변경', attention: '확인 필요' },
  pl: { title: 'Ostatnie zmiany wywiadowcze', subtitle: 'Zmiany z podaniem źródeł, wyprowadzone z autoryzowanej historii. Opisują obserwacje, nie zamiary strategiczne.', empty: 'Brak istotnych zmian wywiadowczych dla tego zakresu.', source: 'Źródło', observed: 'Zaobserwowano', baseline: 'Punkt odniesienia', current: 'Bieżące', previous: 'Poprzednie', viewSource: 'Zobacz źródło', material: 'Istotna zmiana', attention: 'Wymaga weryfikacji' },
  'pt-BR': { title: 'Mudanças recentes de inteligência', subtitle: 'Mudanças com fonte derivadas do histórico autorizado. Descrevem observações, não intenção estratégica.', empty: 'Não há mudanças materiais de inteligência disponíveis para este escopo.', source: 'Fonte', observed: 'Observado', baseline: 'Referência', current: 'Atual', previous: 'Anterior', viewSource: 'Ver fonte', material: 'Mudança material', attention: 'Requer verificação' },
  ru: { title: 'Последние изменения разведданных', subtitle: 'Изменения с указанием источников, полученные из разрешённой истории. Они описывают наблюдения, а не стратегические намерения.', empty: 'Для этой области нет существенных изменений разведданных.', source: 'Источник', observed: 'Наблюдение', baseline: 'База', current: 'Текущее', previous: 'Предыдущее', viewSource: 'Открыть источник', material: 'Существенное изменение', attention: 'Требует проверки' },
  th: { title: 'การเปลี่ยนแปลงข่าวกรองล่าสุด', subtitle: 'การเปลี่ยนแปลงพร้อมแหล่งอ้างอิงจากประวัติที่ได้รับอนุญาต อธิบายสิ่งที่สังเกตได้ ไม่อนุมานเจตนาเชิงกลยุทธ์', empty: 'ไม่มีการเปลี่ยนแปลงข่าวกรองที่มีนัยสำคัญสำหรับขอบเขตนี้', source: 'แหล่งข้อมูล', observed: 'สังเกตเมื่อ', baseline: 'ค่าฐาน', current: 'ปัจจุบัน', previous: 'ก่อนหน้า', viewSource: 'ดูแหล่งข้อมูล', material: 'การเปลี่ยนแปลงสำคัญ', attention: 'ต้องตรวจสอบ' },
  tr: { title: 'Son istihbarat değişiklikleri', subtitle: 'Yetkili geçmişten türetilen, kaynak gösterilen değişiklikler. Gözlemleri açıklar; stratejik niyet çıkarmaz.', empty: 'Bu kapsam için önemli bir istihbarat değişikliği yok.', source: 'Kaynak', observed: 'Gözlemlendi', baseline: 'Temel', current: 'Güncel', previous: 'Önceki', viewSource: 'Kaynağı görüntüle', material: 'Önemli değişiklik', attention: 'Doğrulama gerekli' },
  vi: { title: 'Thay đổi tình báo gần đây', subtitle: 'Các thay đổi có trích nguồn được suy ra từ lịch sử đã được cấp quyền. Chúng mô tả quan sát, không suy đoán ý định chiến lược.', empty: 'Không có thay đổi tình báo đáng kể cho phạm vi này.', source: 'Nguồn', observed: 'Quan sát', baseline: 'Mốc', current: 'Hiện tại', previous: 'Trước đó', viewSource: 'Xem nguồn', material: 'Thay đổi đáng kể', attention: 'Cần xác minh' },
  'zh-CN': { title: '最近的情报变化', subtitle: '基于已授权历史并附带来源的变化。它们描述观察事实，不推断战略意图。', empty: '此范围内没有可用的重大情报变化。', source: '来源', observed: '观察时间', baseline: '基准', current: '当前', previous: '之前', viewSource: '查看来源', material: '重大变化', attention: '需要核实' },
  'zh-TW': { title: '最近的情報變化', subtitle: '基於已授權歷史並附帶來源的變化。它們描述觀察事實，不推斷策略意圖。', empty: '此範圍內沒有可用的重大情報變化。', source: '來源', observed: '觀察時間', baseline: '基準', current: '目前', previous: '之前', viewSource: '查看來源', material: '重大變化', attention: '需要核實' },
};

export function intelligenceChangeLabels(locale: LocaleCode): MessageCatalogue {
  return { intelligenceChange: labels[locale] ?? labels.en };
}
