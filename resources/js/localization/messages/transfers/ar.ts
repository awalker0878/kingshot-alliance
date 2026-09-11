import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    workflowHistoryUnavailable: 'تعذر تحميل سجل سير عمل النقل.',
    workflowHistoryTotal: 'عدد السجلات في هذا السجل: {count}.',
    observationHistoryUnavailable: 'تعذر تحميل سجل الملاحظات.',
    reloadHistory: 'إعادة تحميل السجل',
    eyebrow: 'انتقال المملكة',
    title: 'تخطيط الانتقال',
    readinessBoard: 'الجاهزية',
    completion: 'النتيجة',
    manageTransfers: 'إدارة الانتقالات',
    currentCycle: 'الدورة الحالية',
    participants: 'المشاركون',
    incoming: 'قادم',
    outgoing: 'مغادر',
    staying: 'باقٍ',
    transferGroups: 'مجموعات الانتقال',
    player: 'حاكم',
    gamePlayerId: 'معرّف اللعبة للحاكم',
    readinessTitle: 'جاهزية الانتقال',
    completionTitle: 'نتيجة الانتقال',
    recordCompletion: 'تسجيل نتيجة الانتقال',
    rosterHandoffRecorded: 'تم تحديث قائمة التحالف',
    completedStatus: 'مكتمل',
    notCompletedStatus: 'غير مكتمل',
    stateDraft: 'مسودة',
    stateOpen: 'مفتوحة',
    stateLocked: 'مقفلة',
    stateClosed: 'مغلقة',
    stateCancelled: 'ملغاة',
    readinessReady: 'جاهز',
    readinessBlocked: 'متعطل',
    readinessConfirmed: 'مؤكد',
  },
} satisfies MessageCatalogue;

export default messages;
