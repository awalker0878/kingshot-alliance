import type { MessageCatalogue } from '../../types';

const messages = {
  accountExperience: {
    account: {
      eyebrow: 'إدارة الحساب',
      title: 'الحساب والأمان',
      intro: 'أدر هويتك والتحقق وكلمة المرور والمصادقة الثنائية والجلسات النشطة.',
      passwordUpdated: 'تم تحديث كلمة المرور وتسجيل خروج الجلسات الموثقة الأخرى.',
      sessionsRevoked: 'تم تسجيل خروج الجلسات الموثقة الأخرى.',
      twoFactorDisabled: 'تم تعطيل المصادقة الثنائية.',
      profileTitle: 'الملف الشخصي',
      profileIntro: 'يتطلب تغيير البريد الإلكتروني التحقق منه من جديد.',
      timezone: 'المنطقة الزمنية',
      saveProfile: 'حفظ الملف الشخصي',
      emailVerification: 'التحقق من البريد الإلكتروني',
      verified: 'تم التحقق',
      pending: 'معلّق',
      twoFactorState: 'المصادقة الثنائية',
      enabled: 'مفعّلة',
      setupPending: 'الإعداد معلّق',
      notEnabled: 'غير مفعّلة',
      twoFactorTitle: 'المصادقة الثنائية',
      twoFactorIntro:
        'احمِ تسجيل الدخول باستخدام تطبيق مصادقة TOTP. تظهر رموز الاسترداد فقط عند إنشائها أو إعادة توليدها.',
      startSetup: 'بدء الإعداد',
      authenticatorSecret: 'سر تطبيق المصادقة',
      provisioningUri: 'رابط التهيئة',
      authenticationCode: 'رمز المصادقة',
      confirm: 'تأكيد',
      saveRecoveryCodes: 'احفظ رموز الاسترداد الآن',
      recoveryIntro: 'يعمل كل رمز مرة واحدة. خزّنها بعيداً عن هذا الحساب.',
      regenerateRecoveryCodes: 'إعادة توليد رموز الاسترداد',
      disableTwoFactor: 'تعطيل المصادقة الثنائية',
      passwordTitle: 'تغيير كلمة المرور',
      passwordIntro:
        'يؤدي تغيير كلمة المرور إلى إلغاء رموز الوصول الشخصية والجلسات الموثقة الأخرى.',
      currentPassword: 'كلمة المرور الحالية',
      newPassword: 'كلمة المرور الجديدة',
      confirmNewPassword: 'تأكيد كلمة المرور الجديدة',
      updatePassword: 'تحديث كلمة المرور',
      sessionsTitle: 'الجلسات الأخرى',
      sessionsIntro: 'ألغِ كل جلسة موثقة باستثناء هذا الجهاز.',
      signOutOthers: 'تسجيل خروج الأجهزة الأخرى',
      dangerTitle: 'منطقة حساسة',
      deleteAccount: 'حذف الحساب',
    },
    deletion: {
      eyebrow: 'دورة حياة الحساب',
      title: 'حذف الحساب',
      intro:
        'يتضمن الحذف فترة انتظار سبعة أيام. قد تمنع ملكية تحالف نشط أو صلاحيات مسؤول المنصة أو الحجز القانوني تنفيذ الطلب. يتم إخفاء هوية الحسابات المعالجة بدلاً من حذف سجل التدقيق.',
      currentRequest: 'الطلب الحالي',
      status: 'الحالة',
      eligibleAt: 'مؤهل في',
      requestedAt: 'تاريخ الطلب',
      processedAt: 'تمت المعالجة',
      notYet: 'ليس بعد',
      requestTitle: 'طلب الحذف',
      requestIntro:
        'انقل ملكية أي تحالف تملكه أولاً. تُحتفظ السجلات الخاضعة لحجز قانوني أو المطلوبة للأمان والتدقيق بصيغة مستعارة.',
      requestButton: 'طلب حذف الحساب',
      confirm:
        'هل تريد طلب حذف الحساب؟ توجد فترة انتظار سبعة أيام وتُطبّق فحوص الملكية والحجز القانوني.',
      requested: 'تم تسجيل طلب حذف حسابك.',
      backToAccount: 'العودة إلى الحساب والأمان',
    },
  },
} satisfies MessageCatalogue;

export default messages;
