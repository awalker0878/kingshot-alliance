import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'لم يعد الخيار المحدد متاحاً.',
    searchChoices: 'البحث في الخيارات',
    choicePageSummary:
      '{count} خيارات في هذه الصفحة؛ {total} نتيجة مطابقة. مرتبة حسب معرّف السجل الثابت.',
    choicesFailed: 'تعذّر تحميل الخيارات. لم يتغير اختيارك ولا الصفحة المعروضة.',
    retryChoices: 'إعادة تحميل الخيارات',
    recoveryFailed: 'تعذر إكمال الاستعادة. يرجى المحاولة مجددًا.',
    recordCount: '{count} سجل',
    historyUnavailable: 'تعذر تحميل هذه الصفحة.',
    retryPage: 'إعادة تحميل الصفحة',
    eyebrow: 'إدارة المنصة',
    title: 'إدارة المنصة',
    backDashboard: 'العودة إلى الرئيسية',
    capacityTitle: 'السعة والعمليات',
    administrators: 'مسؤولو المنصة',
    provisionAlliance: 'إنشاء تحالف',
    allianceFleet: 'التحالفات',
    legalHolds: 'حماية السجلات',
    localizationRuntime: 'اللغات',
    registeredLocales: 'اللغات المتاحة',
    defaultLocale: 'اللغة الافتراضية',
    direction: 'الاتجاه',
    catalogue: 'كتالوج الترجمة',
    registered: 'متاح',
    missing: 'مفقود',
  },
} satisfies MessageCatalogue;

export default messages;
