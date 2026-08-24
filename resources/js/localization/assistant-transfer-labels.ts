import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

type TransferLabels = {
  transferRequirements: {
    window_phase: string;
    transfer_group: string;
    power_cap: string;
    invitation: string;
    transfer_passes: string;
    in_game_rules: string;
  };
  transferStates: {
    met: string;
    unmet: string;
    unknown: string;
    stale: string;
    conflicting: string;
    not_applicable: string;
  };
  requirementValues: string;
};

const labels = {
  en: { transferRequirements: { window_phase: 'Transfer window phase', transfer_group: 'Transfer group', power_cap: 'Power cap', invitation: 'Invitation', transfer_passes: 'Transfer passes', in_game_rules: 'In-game rules' }, transferStates: { met: 'Met', unmet: 'Unmet', unknown: 'Unknown', stale: 'Stale', conflicting: 'Conflicting', not_applicable: 'Not applicable' }, requirementValues: 'Actual: {actual} · Required: {required}' },
  ar: { transferRequirements: { window_phase: 'مرحلة نافذة الانتقال', transfer_group: 'مجموعة الانتقال', power_cap: 'حد القوة', invitation: 'الدعوة', transfer_passes: 'تصاريح الانتقال', in_game_rules: 'قواعد داخل اللعبة' }, transferStates: { met: 'مستوفى', unmet: 'غير مستوفى', unknown: 'غير معروف', stale: 'قديم', conflicting: 'متعارض', not_applicable: 'غير منطبق' }, requirementValues: 'الفعلي: {actual} · المطلوب: {required}' },
  de: { transferRequirements: { window_phase: 'Transferfenster-Phase', transfer_group: 'Transfergruppe', power_cap: 'Machtlimit', invitation: 'Einladung', transfer_passes: 'Transferpässe', in_game_rules: 'Regeln im Spiel' }, transferStates: { met: 'Erfüllt', unmet: 'Nicht erfüllt', unknown: 'Unbekannt', stale: 'Veraltet', conflicting: 'Widersprüchlich', not_applicable: 'Nicht zutreffend' }, requirementValues: 'Ist: {actual} · Erforderlich: {required}' },
  es: { transferRequirements: { window_phase: 'Fase de la ventana de transferencia', transfer_group: 'Grupo de transferencia', power_cap: 'Límite de poder', invitation: 'Invitación', transfer_passes: 'Pases de transferencia', in_game_rules: 'Reglas del juego' }, transferStates: { met: 'Cumplido', unmet: 'No cumplido', unknown: 'Desconocido', stale: 'Desactualizado', conflicting: 'En conflicto', not_applicable: 'No aplicable' }, requirementValues: 'Actual: {actual} · Requerido: {required}' },
  fr: { transferRequirements: { window_phase: 'Phase de la fenêtre de transfert', transfer_group: 'Groupe de transfert', power_cap: 'Plafond de puissance', invitation: 'Invitation', transfer_passes: 'Passes de transfert', in_game_rules: 'Règles en jeu' }, transferStates: { met: 'Satisfait', unmet: 'Non satisfait', unknown: 'Inconnu', stale: 'Périmé', conflicting: 'Contradictoire', not_applicable: 'Non applicable' }, requirementValues: 'Actuel : {actual} · Requis : {required}' },
  id: { transferRequirements: { window_phase: 'Fase jendela transfer', transfer_group: 'Grup transfer', power_cap: 'Batas power', invitation: 'Undangan', transfer_passes: 'Transfer Pass', in_game_rules: 'Aturan dalam game' }, transferStates: { met: 'Terpenuhi', unmet: 'Belum terpenuhi', unknown: 'Tidak diketahui', stale: 'Kedaluwarsa', conflicting: 'Bertentangan', not_applicable: 'Tidak berlaku' }, requirementValues: 'Aktual: {actual} · Diperlukan: {required}' },
  it: { transferRequirements: { window_phase: 'Fase finestra di trasferimento', transfer_group: 'Gruppo di trasferimento', power_cap: 'Limite potenza', invitation: 'Invito', transfer_passes: 'Pass trasferimento', in_game_rules: 'Regole in gioco' }, transferStates: { met: 'Soddisfatto', unmet: 'Non soddisfatto', unknown: 'Sconosciuto', stale: 'Obsoleto', conflicting: 'In conflitto', not_applicable: 'Non applicabile' }, requirementValues: 'Attuale: {actual} · Richiesto: {required}' },
  ja: { transferRequirements: { window_phase: '移転期間フェーズ', transfer_group: '移転グループ', power_cap: '戦力上限', invitation: '招待', transfer_passes: '移転パス', in_game_rules: 'ゲーム内ルール' }, transferStates: { met: '達成', unmet: '未達成', unknown: '不明', stale: '古い', conflicting: '競合', not_applicable: '対象外' }, requirementValues: '現在: {actual} · 必要: {required}' },
  ko: { transferRequirements: { window_phase: '이전 기간 단계', transfer_group: '이전 그룹', power_cap: '전투력 상한', invitation: '초대', transfer_passes: '이전 패스', in_game_rules: '게임 내 규칙' }, transferStates: { met: '충족', unmet: '미충족', unknown: '알 수 없음', stale: '오래됨', conflicting: '충돌', not_applicable: '해당 없음' }, requirementValues: '현재: {actual} · 필요: {required}' },
  pl: { transferRequirements: { window_phase: 'Faza okna transferu', transfer_group: 'Grupa transferowa', power_cap: 'Limit mocy', invitation: 'Zaproszenie', transfer_passes: 'Przepustki transferowe', in_game_rules: 'Zasady w grze' }, transferStates: { met: 'Spełnione', unmet: 'Niespełnione', unknown: 'Nieznane', stale: 'Nieaktualne', conflicting: 'Sprzeczne', not_applicable: 'Nie dotyczy' }, requirementValues: 'Aktualne: {actual} · Wymagane: {required}' },
  'pt-BR': { transferRequirements: { window_phase: 'Fase da janela de transferência', transfer_group: 'Grupo de transferência', power_cap: 'Limite de poder', invitation: 'Convite', transfer_passes: 'Passes de transferência', in_game_rules: 'Regras no jogo' }, transferStates: { met: 'Atendido', unmet: 'Não atendido', unknown: 'Desconhecido', stale: 'Desatualizado', conflicting: 'Conflitante', not_applicable: 'Não aplicável' }, requirementValues: 'Atual: {actual} · Necessário: {required}' },
  ru: { transferRequirements: { window_phase: 'Фаза окна переноса', transfer_group: 'Группа переноса', power_cap: 'Лимит силы', invitation: 'Приглашение', transfer_passes: 'Пропуска переноса', in_game_rules: 'Правила в игре' }, transferStates: { met: 'Выполнено', unmet: 'Не выполнено', unknown: 'Неизвестно', stale: 'Устарело', conflicting: 'Противоречиво', not_applicable: 'Не применимо' }, requirementValues: 'Фактически: {actual} · Требуется: {required}' },
  th: { transferRequirements: { window_phase: 'ช่วงหน้าต่างการย้าย', transfer_group: 'กลุ่มการย้าย', power_cap: 'ขีดจำกัดพลัง', invitation: 'คำเชิญ', transfer_passes: 'บัตรย้าย', in_game_rules: 'กฎในเกม' }, transferStates: { met: 'ผ่าน', unmet: 'ไม่ผ่าน', unknown: 'ไม่ทราบ', stale: 'เก่า', conflicting: 'ขัดแย้ง', not_applicable: 'ไม่เกี่ยวข้อง' }, requirementValues: 'ปัจจุบัน: {actual} · ต้องการ: {required}' },
  tr: { transferRequirements: { window_phase: 'Transfer penceresi aşaması', transfer_group: 'Transfer grubu', power_cap: 'Güç sınırı', invitation: 'Davet', transfer_passes: 'Transfer geçişleri', in_game_rules: 'Oyun içi kurallar' }, transferStates: { met: 'Karşılandı', unmet: 'Karşılanmadı', unknown: 'Bilinmiyor', stale: 'Eski', conflicting: 'Çelişkili', not_applicable: 'Uygulanamaz' }, requirementValues: 'Mevcut: {actual} · Gerekli: {required}' },
  vi: { transferRequirements: { window_phase: 'Giai đoạn cửa sổ chuyển', transfer_group: 'Nhóm chuyển', power_cap: 'Giới hạn lực chiến', invitation: 'Lời mời', transfer_passes: 'Thẻ chuyển', in_game_rules: 'Quy tắc trong game' }, transferStates: { met: 'Đạt', unmet: 'Chưa đạt', unknown: 'Chưa biết', stale: 'Đã cũ', conflicting: 'Mâu thuẫn', not_applicable: 'Không áp dụng' }, requirementValues: 'Hiện tại: {actual} · Yêu cầu: {required}' },
  'zh-CN': { transferRequirements: { window_phase: '转服窗口阶段', transfer_group: '转服分组', power_cap: '战力上限', invitation: '邀请', transfer_passes: '转服券', in_game_rules: '游戏内规则' }, transferStates: { met: '已满足', unmet: '未满足', unknown: '未知', stale: '已过期', conflicting: '有冲突', not_applicable: '不适用' }, requirementValues: '当前：{actual} · 要求：{required}' },
  'zh-TW': { transferRequirements: { window_phase: '轉服窗口階段', transfer_group: '轉服分組', power_cap: '戰力上限', invitation: '邀請', transfer_passes: '轉服券', in_game_rules: '遊戲內規則' }, transferStates: { met: '已滿足', unmet: '未滿足', unknown: '未知', stale: '已過期', conflicting: '有衝突', not_applicable: '不適用' }, requirementValues: '目前：{actual} · 要求：{required}' },
} satisfies Record<LocaleCode, TransferLabels>;

export function assistantTransferLabels(locale: LocaleCode): MessageCatalogue {
  return { assistant: labels[locale] };
}
