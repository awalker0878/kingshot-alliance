import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'تجنيد التحالف',
    title: 'التجنيد',
    candidates: 'المرشحون',
    accepted: 'مقبول',
    joined: 'انضم',
    pipeline: 'التجنيد',
    backToPipeline: 'العودة إلى التجنيد',
    stage: 'المرحلة',
    source: 'المصدر',
    submitted: 'تاريخ التقديم',
    nextAction: 'الإجراء التالي',
    bulkActions: 'تغييرات مراحل المرشحين',
    selectedCandidates: 'تم اختيار {count} من المرشحين',
    bulkPreviewHelp: 'راجع من يمكن نقله قبل تطبيق التغيير. المرشحون غير المؤهلين لن يتغيروا.',
    previewBulkAction: 'معاينة تغيير المرحلة',
    bulkPreview: 'معاينة تغيير مراحل المرشحين',
    bulkPreviewSummary:
      '{ready} يمكن تحديثهم و{blocked} يحتاجون إلى مراجعة أو هم بالفعل في المرحلة المطلوبة.',
    confirmBulkTitle: 'تأكيد تغيير المرحلة',
    confirmBulkDescription: 'نقل {count} من المرشحين المؤهلين إلى {stage}؟',
    confirmBulkAction: 'تحديث المرشحين المؤهلين',
    bulkResult: 'نتيجة تغيير المرحلة',
    bulkResultSummary:
      'تم تحديث {succeeded}. يحتاج {failed} إلى مراجعة. كان {skipped} محدثًا بالفعل.',
    failedItemsSelected: 'يبقى المرشحون الذين تعذر تحديثهم محددين لتتمكن من مراجعتهم.',
    settings: 'إعدادات التقديم',
    questions: 'أسئلة التقديم',
    onboarding: 'قائمة التهيئة',
    choosePlayer: 'اختر حاكمًا',
    privateNotes: 'ملاحظات المجند الخاصة',
    stageHistory: 'سجل المراحل',
  },
} satisfies MessageCatalogue;

export default messages;
