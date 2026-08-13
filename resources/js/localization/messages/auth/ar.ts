import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'تسجيل الدخول',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      remember: 'تذكرني',
      forgotPassword: 'نسيت كلمة المرور؟',
      submit: 'تسجيل الدخول',
      createAccount: 'إنشاء حساب',
      invitation: 'لديك دعوة؟',
    },
    register: {
      title: 'إنشاء حساب',
      name: 'الاسم',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      passwordConfirmation: 'تأكيد كلمة المرور',
      submit: 'إنشاء حساب',
      existingAccount: 'لديك حساب بالفعل؟',
    },
    password: {
      forgotTitle: 'إعادة تعيين كلمة المرور',
      forgotDescription: 'أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.',
      sendResetLink: 'إرسال رابط إعادة تعيين كلمة المرور',
      resetTitle: 'اختر كلمة مرور جديدة',
      resetSubmit: 'إعادة تعيين كلمة المرور',
      confirmTitle: 'تأكيد كلمة المرور',
    },
    verification: {
      title: 'تحقق من بريدك الإلكتروني',
      resend: 'إعادة إرسال رسالة التحقق',
    },
    twoFactor: {
      title: 'المصادقة الثنائية',
      code: 'رمز المصادقة',
      recoveryCode: 'رمز الاسترداد',
      submit: 'متابعة',
    },
    invitation: {
      title: 'دعوة التحالف',
      accept: 'قبول الدعوة',
    },
  },
  authExperience: {
    shell: {
      headline: 'مصمم لقادة التحالفات.',
      intro: 'وصول آمن إلى الأدوات التي يستخدمها تحالفك للتنسيق والتجنيد والاستعداد لما هو قادم.',
    },
    login: {
      intro: 'ادخل إلى جميع التحالفات المرتبطة بحسابك العام.',
      invitationNotice: 'سجّل الدخول بالحساب المدعو لمتابعة قبول دعوة التحالف.',
      needAccount: 'تحتاج إلى حساب؟',
      register: 'إنشاء حساب',
    },
    register: {
      intro: 'يمكن لهوية عالمية واحدة أن تنتمي إلى عدة تحالفات.',
      invitationNotice:
        'تمت دعوتك إلى {alliance} باستخدام {email}. إنشاء الحساب سيقبل هذه الدعوة أيضًا.',
      invitationOnly: 'التسجيل متاح حاليًا بالدعوة فقط. افتح رابط الدعوة الذي أرسله تحالفك.',
      timezone: 'المنطقة الزمنية',
      passwordHint: '12 حرفًا على الأقل مع أحرف كبيرة وصغيرة ورقم.',
      existingAccount: 'لديك حساب بالفعل؟',
    },
    invitation: {
      join: 'انضم إلى {alliance}',
      forEmail: 'هذه الدعوة مخصصة لـ {email}.',
      expires: 'تنتهي في {date}',
      wrongAccount: 'أنت مسجّل الدخول باسم {email}. سجّل الدخول بالبريد المدعو لقبول هذه الدعوة.',
      createAndJoin: 'أنشئ حسابًا وانضم',
      signInAccept: 'سجّل الدخول للقبول',
    },
    password: {
      backToSignIn: 'العودة إلى تسجيل الدخول',
      resetIntro: 'إعادة تعيين كلمة المرور تلغي رموز الوصول الشخصية.',
      newPassword: 'كلمة المرور الجديدة',
      confirmNewPassword: 'تأكيد كلمة المرور الجديدة',
      confirmDescription:
        'هذا الإجراء يغيّر وصول التحالف أو الأذونات، لذا يجب إعادة تأكيد كلمة المرور.',
    },
    verification: {
      description:
        'أرسلنا رابط تحقق إلى {email}. تحقّق من العنوان قبل تنفيذ إجراءات الحساب المحمية.',
      sent: 'تم إرسال رابط تحقق جديد.',
    },
    twoFactor: {
      kicker: 'فحص أمني',
      description: 'أدخل الرمز الحالي المكوّن من ستة أرقام من تطبيق المصادقة.',
      verifyCode: 'تحقق من الرمز',
      useRecoveryCode: 'استخدم رمز الاسترداد',
    },
  },
} satisfies MessageCatalogue;

export default messages;
