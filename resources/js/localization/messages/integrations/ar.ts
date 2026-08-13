import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'تكاملات التحالف',
    title: 'بيانات API وخطافات الويب',
    activeCredentials: 'بيانات الاعتماد النشطة',
    activeWebhooks: 'خطافات الويب النشطة',
    recentDeliveries: 'عمليات التسليم الأخيرة',
    apiCredentials: 'بيانات اعتماد API',
    createCredential: 'إنشاء بيانات اعتماد',
    revoke: 'إلغاء',
    webhookSubscriptions: 'اشتراكات خطاف الويب',
    createWebhook: 'إنشاء خطاف ويب',
    deliveryLog: 'سجل التسليم الأخير',
    event: 'الحدث',
    status: 'الحالة',
    attempts: 'المحاولات',
    lastError: 'آخر خطأ',
  },
} satisfies MessageCatalogue;

export default messages;
