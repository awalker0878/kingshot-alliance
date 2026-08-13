import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: 'انتقالات المملكة',
    title: 'تخطيط الانتقالات',
    readinessBoard: 'لوحة الجاهزية',
    completion: 'الإكمال',
    manageTransfers: 'إدارة الانتقالات',
    currentCycle: 'الدورة الحالية',
    participants: 'المشاركون',
    incoming: 'قادم',
    outgoing: 'مغادر',
    staying: 'باقٍ',
    transferGroups: 'مجموعات الانتقال',
    readinessTitle: 'جاهزية الانتقال',
    completionTitle: 'إكمال الانتقال الصريح',
    stateDraft: 'مسودة',
    stateOpen: 'مفتوحة',
    stateLocked: 'مقفلة',
    stateClosed: 'مغلقة',
    stateCancelled: 'ملغاة',
    readinessReady: 'جاهز',
    readinessBlocked: 'محظور',
    readinessConfirmed: 'مؤكد',
  },
} satisfies MessageCatalogue;

export default messages;
