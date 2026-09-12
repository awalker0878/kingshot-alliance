import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} سجلًا في هذه الصفحة · الإجمالي {total}',
    collectionFailed: 'تعذر تحميل القائمة. أعد المحاولة من الصفحة الأولى.',
    selectedUnavailable: 'العنصر المحدد غير متاح',
    reviewOnThisPage: 'تغطي قائمة المراجعة هذه صفحة الفهرس الحالية.',
    allStatuses: 'كل الحالات',
    retryCandidateSummary:
      'يُعرض {selected} لإعادة المحاولة من أصل {total} حالات فشل دون حد المحاولات.',
    eyebrow: 'محتوى التحالف',
    hubTitle: 'مركز المحتوى',
    manageContent: 'إدارة المحتوى',
    results: 'النتائج',
    publicItems: 'عام',
    memberItems: 'للأعضاء فقط',
    categories: 'الفئات',
    search: 'البحث في محتوى التحالف',
    type: 'نوع المحتوى',
    category: 'الفئة',
    locale: 'اللغة',
    applyFilters: 'تطبيق المرشحات',
    clear: 'مسح',
    publishedContent: 'المحتوى المنشور',
    publicProfile: 'الملف العام للتحالف',
    createContent: 'إنشاء محتوى',
    editContent: 'تعديل المحتوى',
    mediaLibrary: 'مكتبة الوسائط',
    contentInventory: 'قائمة المحتوى',
  },
} satisfies MessageCatalogue;

export default messages;
