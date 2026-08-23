import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'مراجعة صيد الدب',
    eyebrow: 'صيد الدب · ما بعد الحدث',
    subtitle:
      'راجع الضرر المسجل والحضور والمشاركة في التجمعات والحكام غير المطابقين وقارن هذه الجولة بالجولات الأخيرة.',
    totalDamage: 'إجمالي الضرر',
    governors: 'الحكام',
    governor: 'الحاكم',
    governorCount: '{count} حكام',
    attendance: 'الحضور',
    recordedRallies: 'التجمعات المسجلة',
    notRecorded: 'غير مسجل',
    notComparable: 'لا تتوفر مقارنة',
    noChange: 'لا تغيير عن الصيد السابق',
    increased: 'ارتفع',
    decreased: 'انخفض',
    changeWithPercent: '{direction} بمقدار {amount} ({percent}٪) مقارنة بالصيد السابق',
    change: '{direction} بمقدار {amount} مقارنة بالصيد السابق',
    rankUp: 'تقدم {count} مراكز عن الصيد السابق',
    rankDown: 'تراجع {count} مراكز عن الصيد السابق',
    yourHunt: 'صيدك',
    damage: 'الضرر',
    rank: 'الترتيب',
    alliancePerformance: 'أداء التحالف',
    leaderboard: 'ترتيب الحكام',
    reportCount: '{count} تقارير معركة مسجلة',
    unknownGovernor: 'حاكم غير معروف',
    noResults: 'لم يتم تسجيل ضرر للحكام في هذا الصيد بعد.',
    needsReview: 'يحتاج إلى مراجعة',
    unmatchedGovernors: '{count} حكام يحتاجون إلى مطابقة',
    reviewHelp:
      'أكمل مطابقة الحكام في Screenshot Intake. لا تنشئ المراجعة مسار هوية ثانياً.',
    reviewImport: 'مراجعة التقرير المستورد',
    trends: 'الاتجاهات',
    runTrends: 'اتجاهات صيد الدب الأخيرة',
    yourDamageTrend: 'ضررك حسب الصيد',
    allianceDamageTrend: 'ضرر التحالف حسب الصيد',
    previousHunt: 'الصيد السابق',
    noPrevious: 'لا يوجد صيد سابق',
    noPreviousHelp: 'تظهر المقارنة بعد وجود صيد دب سابق مكتمل لهذا التحالف.',
    history: 'السجل',
    runHistory: 'سجل صيد الدب',
  },
} satisfies MessageCatalogue;

export default messages;
