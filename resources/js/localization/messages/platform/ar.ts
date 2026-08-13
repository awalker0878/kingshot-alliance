import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    eyebrow: 'عمليات المنصة',
    title: 'إدارة المنصة',
    backDashboard: 'العودة إلى لوحة التحكم',
    capacityTitle: 'السعة والعمليات',
    administrators: 'مسؤولو المنصة',
    provisionAlliance: 'إنشاء التحالف',
    allianceFleet: 'أسطول التحالفات',
    legalHolds: 'الحجوزات القانونية',
    localizationRuntime: 'بيئة الترجمة',
    registeredLocales: 'اللغات المسجلة',
    defaultLocale: 'اللغة الافتراضية',
    direction: 'الاتجاه',
    catalogue: 'الكتالوج',
    registered: 'مسجل',
    missing: 'مفقود',
  },
} satisfies MessageCatalogue;

export default messages;
