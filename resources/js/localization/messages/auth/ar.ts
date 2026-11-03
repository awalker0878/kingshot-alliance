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
      forgotTitle: 'استرداد حسابك',
      forgotDescription: 'أدخل عنوان البريد الإلكتروني المرتبط بحسابك في Kingshot Alliance.',
      sendResetLink: 'إرسال تعليمات الاسترداد',
      resetTitle: 'اختر كلمة مرور جديدة',
      resetSubmit: 'إعادة تعيين كلمة المرور',
      confirmTitle: 'تأكيد هويتك',
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
      intro: 'جنّد الحكام، وجهّز الفعاليات، وأدِر تحالفك، وتابع المملكة من مكان واحد.',
    },
    login: {
      intro: 'سجّل الدخول بحسابك، ثم اختر الحاكم الذي تريد استخدامه.',
      invitationNotice: 'سجّل الدخول بالحساب المدعو لمتابعة قبول دعوة التحالف.',
      needAccount: 'تحتاج إلى حساب؟',
      register: 'إنشاء حساب',
    },
    register: {
      intro:
        'يمكن أن يضم الحساب الواحد عدة حكام. ترتبط رتبة التحالف ومهام المملكة دائمًا بالحاكم الذي تختاره.',
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
      resetIntro: 'اختر كلمة مرور جديدة لحساب Kingshot Alliance الموضح أدناه.',
      newPassword: 'كلمة المرور الجديدة',
      confirmNewPassword: 'تأكيد كلمة المرور الجديدة',
      confirmDescription:
        'يغيّر هذا الإجراء الوصول المحمي إلى التحالف، لذا أكّد هويتك قبل المتابعة.',
      recoveryKicker: 'استرداد الحساب',
      recoveryNeutral:
        'إذا كان استرداد كلمة المرور متاحًا لعنوان البريد الإلكتروني هذا، فسنرسل تعليمات آمنة لإعادة تعيينها.',
      checkInboxKicker: 'تحقق من صندوق الوارد',
      checkInboxHeading: 'تم طلب تعليمات الاسترداد',
      checkInboxDescription:
        'إذا كان استرداد كلمة المرور متاحًا للعنوان الذي أدخلته، فقد أُرسلت تعليمات إعادة تعيينها. لأسباب أمنية، لا يمكننا تأكيد وجود حساب أو طرق تسجيل الدخول التي يستخدمها.',
      resetKicker: 'إعادة تعيين كلمة المرور بأمان',
      accountEmail: 'البريد الإلكتروني للحساب',
      requirements: 'يجب أن تتضمن كلمة المرور:',
      requirementLength: '12 حرفا على الأقل',
      requirementUpper: 'حرف كبير',
      requirementLower: 'حرف صغير',
      requirementNumber: 'رقم',
      showPassword: 'أظهر كلمة المرور',
      hidePassword: 'إخفاء كلمة المرور',
      validationSummary: 'راجع الحقول المحددة قبل المضي قدما.',
    },
    verification: {
      description: 'أرسلنا رابط تحقق آمنًا لحسابك في Kingshot Alliance إلى:',
      sent: 'تم إرسال رابط تحقق جديد.',
      kicker: 'التحقق من البريد الإلكتروني',
      instructions: 'افتح الرسالة واتبع الرابط لإكمال التحقق من عنوان بريدك الإلكتروني.',
      securityNote:
        'لن يطلب منك Kingshot Alliance كلمة مرورك أبدًا عبر رسالة التحقق من البريد الإلكتروني.',
      resendIn: 'يمكنك إعادة الإرسال بعد {seconds} ثانية',
      useAnotherAccount: 'الخروج واستخدام حساب آخر',
    },
    twoFactor: {
      kicker: 'أمان الحساب',
      description: 'أدخل الرمز الحالي المكوّن من ستة أرقام من تطبيق المصادقة.',
      verifyCode: 'تحقق من الرمز',
      useRecoveryCode: 'استخدم رمز الاسترداد',
    },
    social: {
      continueWithGoogle: 'المتابعة باستخدام Google',
      createWithGoogle: 'إنشاء حساب Kingshot Alliance باستخدام Google',
      or: 'أو',
    },
    passkeys: {
      signIn: 'تسجيل الدخول بمفتاح المرور',
      authenticating: 'جارٍ التحقق من مفتاح المرور…',
    },
    confirm: {
      title: 'تأكيد هويتك',
      description:
        'استخدم إحدى طرق تسجيل الدخول المرتبطة بحسابك قبل متابعة هذا التغيير الحساس في الحساب.',
      usePassword: 'كلمة المرور',
      useGoogle: 'يرجى التأكد من Google',
      usePasskey: 'التأكيد بمفتاح المرور',
    },
  },
} satisfies MessageCatalogue;

export default messages;
