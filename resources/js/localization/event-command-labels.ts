import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

const englishCommand: MessageCatalogue = {
  eyebrow: 'Event Command',
  title: 'Readiness',
  closeoutEyebrow: 'Event Command',
  closeoutTitle: 'Closeout',
  occurrence: 'Occurrence',
  noOccurrence: 'No Event occurrence is available to assess.',
  blockers: '{count} blockers',
  warnings: '{count} warnings',
  readinessClear: 'No readiness blockers',
  closeoutClear: 'Closeout clear',
  attentionItems: 'Event Command items needing attention',
  completedChecks: 'Completed and informational checks',
  owner: 'Owner: {owner}',
  states: {
    planning: 'Planning',
    needs_attention: 'Needs attention',
    ready: 'Ready',
    active: 'Active',
    closeout_required: 'Closeout required',
    complete: 'Complete',
    cancelled: 'Cancelled',
    unavailable: 'Unavailable',
  },
  status: {
    complete: 'Complete',
    needs_attention: 'Needs attention',
    warning: 'Warning',
    unknown: 'Unknown',
    not_applicable: 'Not applicable',
  },
  owners: {
    'operations.events': 'Events',
    'operations.participation': 'Participation',
    'operations.polls': 'Polls',
    'operations.rosters': 'Rosters',
    'operations.battle_plans': 'Battle Plans',
    'alliance.content': 'Alliance Content',
    'operations.territory_planning': 'Territory Planning',
    'operations.reminders': 'Event Reminders',
    'communications.delivery': 'Communications',
    'operations.rallies': 'Rallies',
    'operations.results': 'Results',
    'intelligence.evidence': 'Evidence',
    'readmodels.event_analysis': 'Event Analysis',
  },
  classifications: {
    alliance_strategy: 'Alliance strategy',
    evidence: 'Evidence workflow',
  },
  actions: {
    reviewSchedule: 'Review schedule',
    reviewParticipation: 'Review participation',
    reviewPolls: 'Review polls',
    openRoster: 'Open roster',
    openBattlePlan: 'Open Battle Plan',
    openStrategy: 'Open Alliance strategy',
    openTerritory: 'Open Territory plan',
    manageReminder: 'Manage reminder',
    reviewDelivery: 'Review delivery',
    openRallies: 'Open Rally plan',
    recordAttendance: 'Record attendance',
    recordRallyParticipation: 'Record Rally participation',
    recordResults: 'Record results',
    reviewEvidence: 'Review Evidence',
    recoverEvidence: 'Recover Evidence',
    openDebrief: 'Open Debrief',
    openOwnerWorkflow: 'Open owner workflow',
  },
  sections: {
    schedule: 'Schedule',
    participation: 'Attendance & registration',
    polls: 'Polls',
    rosters: 'Roster',
    battlePlan: 'Battle Plan',
    strategy: 'Alliance strategy',
    territory: 'Territory',
    communications: 'Communications',
    rallies: 'Rallies',
    attendance: 'Attendance',
    results: 'Results',
    evidence: 'Evidence',
    debrief: 'Debrief',
  },
  items: {
    scheduleReady: 'Occurrence schedule and timezone are valid.',
    scheduleInvalid: 'Occurrence schedule or timezone needs correction.',
    cancelled: 'This occurrence is cancelled; readiness and closeout do not apply.',
    participationUnavailable: 'Participation status is temporarily unavailable.',
    unansweredMembers: '{count} Governors have not answered.',
    responsesComplete: 'All eligible Governors have answered.',
    registrationState: '{registeredCount} registered · {waitlistCount} waitlisted · registration {state}.',
    waitlistActive: '{count} Governors are on the waitlist.',
    waitlistClear: 'No Governors are waitlisted.',
    pollsUnavailable: 'Poll readiness is temporarily unavailable.',
    pollsUnresolved: '{count} planning polls are still draft or open.',
    pollsResolved: 'Configured planning polls are resolved.',
    rosterUnavailable: 'Roster readiness is temporarily unavailable.',
    rosterMissing: 'No Event roster has been configured.',
    rosterUnfilled: '{count} roster slots are unfilled.',
    rosterFilled: 'Required roster slots are filled.',
    rosterUnassigned: '{count} rostered Governors do not have a slot.',
    rosterAssigned: 'Rostered Governors have their roster slots.',
    rosterWarnings: '{count} roster assignment warnings need review.',
    battlePlanUnavailable: 'Battle Plan readiness is temporarily unavailable.',
    objectivesMissing: 'No Battle Plan objectives are configured.',
    objectivesConfigured: '{count} Battle Plan objectives are configured.',
    battlePlanUnassigned: '{count} rostered Governors have no Battle Plan assignment.',
    battlePlanAssigned: 'Rostered Governors have Battle Plan assignments.',
    battlePlanInvalidAssignments: '{count} Battle Plan assignments reference an invalid target.',
    ralliesUnavailable: 'Rally status is temporarily unavailable.',
    ralliesMissing: 'No Rally groups are configured.',
    ralliesConfigured: '{count} Rally groups are configured.',
    ralliesWithoutLead: '{count} Rally groups do not have a lead.',
    rallyLeadsReady: 'Configured Rally groups have leads.',
    strategyUnavailable: 'Alliance strategy status is temporarily unavailable.',
    strategyMissing: 'No published Event-linked Alliance strategy is available.',
    strategyNeedsReview: '{title} is published but its review status is {freshness}.',
    strategyCurrent: '{title} revision {revision} is current.',
    territoryUnavailable: 'Territory status is temporarily unavailable.',
    territoryNotAttached: 'No published Territory revision is attached to this occurrence.',
    territoryViolations: 'The referenced Territory revision has {count} validation violations.',
    territoryReady: 'The referenced published Territory revision is valid.',
    territoryWarnings: 'The referenced Territory revision has {count} validation warnings.',
    territoryDraftDiffers: 'The current Territory draft differs from the immutable Event revision.',
    reminderUnavailable: 'Event reminder configuration is temporarily unavailable.',
    reminderMissing: 'An Event-start reminder has not been scheduled.',
    reminderScheduled: 'An Event-start reminder is scheduled.',
    deliveryUnavailable: 'Reminder delivery health is temporarily unavailable.',
    deliveryFailed: '{count} reminder deliveries failed; {retryableCount} can be retried.',
    deliveryPending: '{count} reminder deliveries are pending or queued, not delivered yet.',
    deliverySent: '{count} reminder deliveries were sent.',
    attendanceUnavailable: 'Attendance closeout status is temporarily unavailable.',
    attendanceMissing: '{count} Governors do not have a recorded attendance result.',
    attendanceComplete: 'Attendance has been recorded for eligible Governors.',
    rallyActualsNotApplicable: 'No planned Rally assignments require participation closeout.',
    rallyActualsMissing: '{count} Rally assignments do not have recorded participation.',
    rallyActualsComplete: 'Rally participation has been recorded.',
    resultsUnavailable: 'Results closeout status is temporarily unavailable.',
    resultsComplete: 'The Event result is recorded.',
    resultsMissing: 'The Event result has not been recorded.',
    playerResultsMissing: '{count} eligible Governors do not have an individual result.',
    correctionsUnsupported: 'Results does not currently expose an explicit unresolved-correction workflow.',
    evidenceUnavailable: 'Evidence closeout status is temporarily unavailable.',
    evidenceProcessing: '{count} Evidence items are still processing.',
    evidenceAwaitingReview: '{count} Evidence items are awaiting review.',
    evidenceUnmatched: '{count} Evidence items contain unmatched Governors.',
    evidenceCommitPending: '{count} reviewed Evidence commits are pending.',
    evidenceFailed: '{count} Evidence processing or commit attempts failed.',
    evidenceClear: 'No Evidence workflow blockers remain; {count} items are committed.',
    debriefAvailable: 'Debrief is available.',
    debriefUnavailable: 'Debrief will become available when its required owner data exists.',
  },
};

type CompactLabels = {
  title: string;
  closeout: string;
  occurrence: string;
  planning: string;
  attention: string;
  ready: string;
  active: string;
  closeoutRequired: string;
  complete: string;
  cancelled: string;
  schedule: string;
  participation: string;
  roster: string;
  battlePlan: string;
  strategy: string;
  territory: string;
  communications: string;
  rallies: string;
  results: string;
  evidence: string;
  debrief: string;
};

const compact: Partial<Record<LocaleCode, CompactLabels>> = {
  ar: { title: 'الجاهزية', closeout: 'الإغلاق', occurrence: 'الموعد', planning: 'التخطيط', attention: 'يتطلب الانتباه', ready: 'جاهز', active: 'نشط', closeoutRequired: 'الإغلاق مطلوب', complete: 'مكتمل', cancelled: 'ملغى', schedule: 'الجدول', participation: 'الحضور والتسجيل', roster: 'القائمة', battlePlan: 'خطة المعركة', strategy: 'استراتيجية التحالف', territory: 'الإقليم', communications: 'الاتصالات', rallies: 'التجمعات', results: 'النتائج', evidence: 'الأدلة', debrief: 'المراجعة' },
  de: { title: 'Bereitschaft', closeout: 'Abschluss', occurrence: 'Termin', planning: 'Planung', attention: 'Handlungsbedarf', ready: 'Bereit', active: 'Aktiv', closeoutRequired: 'Abschluss erforderlich', complete: 'Abgeschlossen', cancelled: 'Abgesagt', schedule: 'Zeitplan', participation: 'Teilnahme & Anmeldung', roster: 'Aufstellung', battlePlan: 'Schlachtplan', strategy: 'Allianzstrategie', territory: 'Territorium', communications: 'Kommunikation', rallies: 'Rallyes', results: 'Ergebnisse', evidence: 'Nachweise', debrief: 'Nachbesprechung' },
  es: { title: 'Preparación', closeout: 'Cierre', occurrence: 'Instancia', planning: 'Planificación', attention: 'Requiere atención', ready: 'Listo', active: 'Activo', closeoutRequired: 'Cierre requerido', complete: 'Completo', cancelled: 'Cancelado', schedule: 'Horario', participation: 'Asistencia y registro', roster: 'Plantilla', battlePlan: 'Plan de batalla', strategy: 'Estrategia de la alianza', territory: 'Territorio', communications: 'Comunicaciones', rallies: 'Rallies', results: 'Resultados', evidence: 'Evidencia', debrief: 'Informe' },
  fr: { title: 'Préparation', closeout: 'Clôture', occurrence: 'Occurrence', planning: 'Planification', attention: 'Attention requise', ready: 'Prêt', active: 'Actif', closeoutRequired: 'Clôture requise', complete: 'Terminé', cancelled: 'Annulé', schedule: 'Horaire', participation: 'Présence et inscription', roster: 'Composition', battlePlan: 'Plan de bataille', strategy: "Stratégie d'Alliance", territory: 'Territoire', communications: 'Communications', rallies: 'Ralliements', results: 'Résultats', evidence: 'Preuves', debrief: 'Débriefing' },
  id: { title: 'Kesiapan', closeout: 'Penutupan', occurrence: 'Kejadian', planning: 'Perencanaan', attention: 'Perlu perhatian', ready: 'Siap', active: 'Aktif', closeoutRequired: 'Perlu penutupan', complete: 'Selesai', cancelled: 'Dibatalkan', schedule: 'Jadwal', participation: 'Kehadiran & pendaftaran', roster: 'Roster', battlePlan: 'Rencana pertempuran', strategy: 'Strategi Aliansi', territory: 'Wilayah', communications: 'Komunikasi', rallies: 'Rally', results: 'Hasil', evidence: 'Bukti', debrief: 'Debrief' },
  it: { title: 'Preparazione', closeout: 'Chiusura', occurrence: 'Occorrenza', planning: 'Pianificazione', attention: 'Richiede attenzione', ready: 'Pronto', active: 'Attivo', closeoutRequired: 'Chiusura richiesta', complete: 'Completo', cancelled: 'Annullato', schedule: 'Programma', participation: 'Presenza e registrazione', roster: 'Roster', battlePlan: 'Piano di battaglia', strategy: 'Strategia Alleanza', territory: 'Territorio', communications: 'Comunicazioni', rallies: 'Rally', results: 'Risultati', evidence: 'Prove', debrief: 'Debrief' },
  ja: { title: '準備状況', closeout: 'クローズアウト', occurrence: '開催回', planning: '計画中', attention: '要対応', ready: '準備完了', active: '進行中', closeoutRequired: 'クローズアウト必要', complete: '完了', cancelled: 'キャンセル', schedule: 'スケジュール', participation: '出欠・登録', roster: 'ロスター', battlePlan: '戦闘計画', strategy: '同盟戦略', territory: '領土', communications: '連絡', rallies: '集結', results: '結果', evidence: '証拠', debrief: '振り返り' },
  ko: { title: '준비 상태', closeout: '마감', occurrence: '회차', planning: '계획 중', attention: '확인 필요', ready: '준비 완료', active: '진행 중', closeoutRequired: '마감 필요', complete: '완료', cancelled: '취소됨', schedule: '일정', participation: '참석 및 등록', roster: '명단', battlePlan: '전투 계획', strategy: '연맹 전략', territory: '영토', communications: '통신', rallies: '집결', results: '결과', evidence: '증거', debrief: '디브리핑' },
  pl: { title: 'Gotowość', closeout: 'Zamknięcie', occurrence: 'Termin', planning: 'Planowanie', attention: 'Wymaga uwagi', ready: 'Gotowe', active: 'Aktywne', closeoutRequired: 'Wymaga zamknięcia', complete: 'Zakończone', cancelled: 'Anulowane', schedule: 'Harmonogram', participation: 'Obecność i rejestracja', roster: 'Skład', battlePlan: 'Plan bitwy', strategy: 'Strategia Sojuszu', territory: 'Terytorium', communications: 'Komunikacja', rallies: 'Rajdy', results: 'Wyniki', evidence: 'Dowody', debrief: 'Podsumowanie' },
  'pt-BR': { title: 'Prontidão', closeout: 'Encerramento', occurrence: 'Ocorrência', planning: 'Planejamento', attention: 'Precisa de atenção', ready: 'Pronto', active: 'Ativo', closeoutRequired: 'Encerramento necessário', complete: 'Concluído', cancelled: 'Cancelado', schedule: 'Agenda', participation: 'Presença e inscrição', roster: 'Escalação', battlePlan: 'Plano de batalha', strategy: 'Estratégia da Aliança', territory: 'Território', communications: 'Comunicações', rallies: 'Rallies', results: 'Resultados', evidence: 'Evidências', debrief: 'Debrief' },
  ru: { title: 'Готовность', closeout: 'Закрытие', occurrence: 'Проведение', planning: 'Планирование', attention: 'Требует внимания', ready: 'Готово', active: 'Активно', closeoutRequired: 'Требуется закрытие', complete: 'Завершено', cancelled: 'Отменено', schedule: 'Расписание', participation: 'Участие и регистрация', roster: 'Состав', battlePlan: 'План боя', strategy: 'Стратегия Альянса', territory: 'Территория', communications: 'Связь', rallies: 'Сборы', results: 'Результаты', evidence: 'Доказательства', debrief: 'Разбор' },
  th: { title: 'ความพร้อม', closeout: 'ปิดงาน', occurrence: 'รอบกิจกรรม', planning: 'กำลังวางแผน', attention: 'ต้องตรวจสอบ', ready: 'พร้อม', active: 'กำลังดำเนินการ', closeoutRequired: 'ต้องปิดงาน', complete: 'เสร็จสิ้น', cancelled: 'ยกเลิก', schedule: 'กำหนดการ', participation: 'การเข้าร่วมและลงทะเบียน', roster: 'รายชื่อ', battlePlan: 'แผนการรบ', strategy: 'กลยุทธ์พันธมิตร', territory: 'อาณาเขต', communications: 'การสื่อสาร', rallies: 'แรลลี่', results: 'ผลลัพธ์', evidence: 'หลักฐาน', debrief: 'สรุปหลังงาน' },
  tr: { title: 'Hazırlık', closeout: 'Kapanış', occurrence: 'Etkinlik', planning: 'Planlama', attention: 'İlgi gerekiyor', ready: 'Hazır', active: 'Aktif', closeoutRequired: 'Kapanış gerekli', complete: 'Tamamlandı', cancelled: 'İptal edildi', schedule: 'Takvim', participation: 'Katılım ve kayıt', roster: 'Kadro', battlePlan: 'Savaş Planı', strategy: 'İttifak stratejisi', territory: 'Bölge', communications: 'İletişim', rallies: 'Toplanmalar', results: 'Sonuçlar', evidence: 'Kanıt', debrief: 'Değerlendirme' },
  vi: { title: 'Mức sẵn sàng', closeout: 'Hoàn tất', occurrence: 'Lần diễn ra', planning: 'Đang lập kế hoạch', attention: 'Cần chú ý', ready: 'Sẵn sàng', active: 'Đang diễn ra', closeoutRequired: 'Cần hoàn tất', complete: 'Hoàn tất', cancelled: 'Đã hủy', schedule: 'Lịch', participation: 'Tham dự và đăng ký', roster: 'Danh sách', battlePlan: 'Kế hoạch chiến đấu', strategy: 'Chiến lược Liên minh', territory: 'Lãnh thổ', communications: 'Liên lạc', rallies: 'Tập kết', results: 'Kết quả', evidence: 'Bằng chứng', debrief: 'Tổng kết' },
  'zh-CN': { title: '准备状态', closeout: '收尾', occurrence: '场次', planning: '规划中', attention: '需要处理', ready: '已就绪', active: '进行中', closeoutRequired: '需要收尾', complete: '已完成', cancelled: '已取消', schedule: '日程', participation: '出席与报名', roster: '名单', battlePlan: '战斗计划', strategy: '联盟策略', territory: '领地', communications: '通信', rallies: '集结', results: '结果', evidence: '证据', debrief: '复盘' },
  'zh-TW': { title: '準備狀態', closeout: '收尾', occurrence: '場次', planning: '規劃中', attention: '需要處理', ready: '已就緒', active: '進行中', closeoutRequired: '需要收尾', complete: '已完成', cancelled: '已取消', schedule: '日程', participation: '出席與報名', roster: '名單', battlePlan: '戰鬥計畫', strategy: '聯盟策略', territory: '領地', communications: '通訊', rallies: '集結', results: '結果', evidence: '證據', debrief: '復盤' },
};

function localizedCommand(locale: LocaleCode): MessageCatalogue {
  const labels = compact[locale];
  if (!labels) return englishCommand;

  return {
    eyebrow: 'Event Command',
    title: labels.title,
    closeoutEyebrow: 'Event Command',
    closeoutTitle: labels.closeout,
    occurrence: labels.occurrence,
    states: {
      planning: labels.planning,
      needs_attention: labels.attention,
      ready: labels.ready,
      active: labels.active,
      closeout_required: labels.closeoutRequired,
      complete: labels.complete,
      cancelled: labels.cancelled,
      unavailable: labels.attention,
    },
    sections: {
      schedule: labels.schedule,
      participation: labels.participation,
      polls: 'Polls',
      rosters: labels.roster,
      battlePlan: labels.battlePlan,
      strategy: labels.strategy,
      territory: labels.territory,
      communications: labels.communications,
      rallies: labels.rallies,
      attendance: labels.participation,
      results: labels.results,
      evidence: labels.evidence,
      debrief: labels.debrief,
    },
  };
}

export function eventCommandLabels(locale: LocaleCode): MessageCatalogue {
  return { events: { command: localizedCommand(locale) } };
}
