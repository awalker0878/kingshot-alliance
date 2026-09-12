import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
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
