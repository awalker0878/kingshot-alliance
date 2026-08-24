import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'التقدم' },
  progression: {
    ...en.progression,
    title: 'التقدم القائم على الحقائق',
    eyebrow: 'بيانات KingShot المرجعية',
    subtitle:
      'بيانات تقدم ذات إصدارات ومصادر واضحة. تبقى القيم المجهولة والتعارضات ظاهرة بدل التخمين.',
    factualOnly: 'مرجع حقائقي فقط.',
    noRecommendations: 'تشكيلات المجتمع أعراف وليست توصيات. تظل الحاسبات خاضعة لبوابة الأدلة.',
    communityConvention: 'عرف مجتمعي',
    sourceConflicts: 'تعارضات المصادر',
    coverage: 'تغطية البيانات',
    sources: 'المصادر والمنشأ',
  },
} satisfies MessageCatalogue;
export default messages;
