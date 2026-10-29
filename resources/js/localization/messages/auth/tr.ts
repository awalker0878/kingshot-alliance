import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Giriş yap',
      email: 'E-posta',
      password: 'Şifre',
      remember: 'Beni hatırla',
      forgotPassword: 'Şifrenizi mi unuttunuz?',
      submit: 'Giriş yap',
      createAccount: 'Hesap oluştur',
      invitation: 'Davetiniz var mı?',
    },
    register: {
      title: 'Hesap oluştur',
      name: 'Ad',
      email: 'E-posta',
      password: 'Şifre',
      passwordConfirmation: 'Şifreyi onayla',
      submit: 'Hesap oluştur',
      existingAccount: 'Zaten hesabınız var mı?',
    },
    password: {
      forgotTitle: 'Hesabını kurtar',
      forgotDescription: 'Kingshot Alliance hesabına bağlı e-posta adresini gir.',
      sendResetLink: 'Kurtarma talimatlarını gönder',
      resetTitle: 'Yeni bir şifre seçin',
      resetSubmit: 'Şifreyi sıfırla',
      confirmTitle: 'Kimliğini doğrula',
    },
    verification: {
      title: 'E-postanızı doğrulayın',
      resend: 'Doğrulama e-postasını yeniden gönder',
    },
    twoFactor: {
      title: 'İki faktörlü kimlik doğrulama',
      code: 'Kimlik doğrulama kodu',
      recoveryCode: 'Kurtarma kodu',
      submit: 'Devam et',
    },
    invitation: {
      title: 'İttifak daveti',
      accept: 'Daveti kabul et',
    },
  },
  authExperience: {
    shell: {
      headline: 'İttifak liderleri için tasarlandı.',
      intro:
        'Valileri topla, Etkinlikleri hazırla, İttifakını yönet ve Krallığı tek bir yerden takip et.',
    },
    login: {
      intro: 'Hesabınla giriş yap, ardından kullanmak istediğin Valiyi seç.',
      invitationNotice:
        'İttifak davetini kabul etmeye devam etmek için davet edilen hesapla giriş yapın.',
      needAccount: 'Hesaba mı ihtiyacınız var?',
      register: 'Kayıt ol',
    },
    register: {
      intro:
        'Bir hesap birden fazla Valiye sahip olabilir. İttifaktaki rütbe ve Krallıktaki görevler her zaman seçtiğin Valiye bağlıdır.',
      invitationNotice:
        '{email} olarak {alliance} ittifakına davet edildiniz. Hesap oluşturmak bu daveti de kabul eder.',
      invitationOnly:
        'Kayıt şu anda yalnızca davetle mümkündür. İttifakınızın gönderdiği davet bağlantısını açın.',
      timezone: 'Saat dilimi',
      passwordHint: 'Büyük harf, küçük harf ve sayı içeren en az 12 karakter.',
      existingAccount: 'Zaten hesabınız var mı?',
    },
    invitation: {
      join: '{alliance} ittifakına katıl',
      forEmail: 'Bu davet {email} içindir.',
      expires: 'Sona erme: {date}',
      wrongAccount:
        '{email} olarak giriş yaptınız. Bu daveti kabul etmek için davet edilen e-posta ile giriş yapın.',
      createAndJoin: 'Hesap oluştur ve katıl',
      signInAccept: 'Kabul etmek için giriş yap',
    },
    password: {
      backToSignIn: 'Girişe dön',
      resetIntro: 'Aşağıdaki hesap için yeni bir Kingshot Alliance şifresi seç.',
      newPassword: 'Yeni şifre',
      confirmNewPassword: 'Yeni şifreyi onayla',
      confirmDescription:
        'Bu işlem İttifaka korumalı erişimi değiştirir. Devam etmeden önce kimliğini doğrula.',
      recoveryKicker: 'HESAP KURTARMA',
      recoveryNeutral:
        'Bu e-posta adresi için şifre kurtarma kullanılabiliyorsa, şifreyi güvenle sıfırlaman için talimatlar göndereceğiz.',
      checkInboxKicker: 'GELEN KUTUNU KONTROL ET',
      checkInboxHeading: 'Kurtarma talimatları istendi',
      checkInboxDescription:
        'Girdiğin adres için şifre kurtarma kullanılabiliyorsa, şifre sıfırlama talimatları gönderildi. Güvenlik nedeniyle bir hesabın var olup olmadığını veya hangi giriş yöntemlerini kullandığını doğrulayamıyoruz.',
      resetKicker: 'GÜVENLİ ŞİFRE SIFIRLAMA',
      accountEmail: 'Hesap e-posta',
      requirements: 'Şifreniz şunu içermelidir:',
      requirementLength: 'En az 12 karakter',
      requirementUpper: 'Büyük harf',
      requirementLower: 'Küçük harf',
      requirementNumber: 'Bir rakam',
      showPassword: 'Şifreyi göster',
      hidePassword: 'Şifreyi Gizle',
      validationSummary: 'Devam etmeden önce işaretlenen alanları inceleyin.',
    },
    verification: {
      description:
        'Kingshot Alliance hesabın için güvenli bir doğrulama bağlantısını şu adrese gönderdik:',
      sent: 'Yeni bir doğrulama bağlantısı gönderildi.',
      kicker: 'E-POSTA DOĞRULAMA',
      instructions: 'E-posta adresinizi doğrulamak için mesajı açın ve bağlantıyı izleyin.',
      securityNote: 'Kingshot Alliance, e-posta doğrulama mesajında asla şifreni istemez.',
      resendIn: '{seconds} saniye sonra yeniden gönderebilirsin',
      useAnotherAccount: 'Çık ve başka bir hesap kullan',
    },
    twoFactor: {
      kicker: 'Hesap güvenliği',
      description: 'Kimlik doğrulama uygulamanızdaki güncel altı haneli kodu girin.',
      verifyCode: 'Kodu doğrula',
      useRecoveryCode: 'Kurtarma kodunu kullan',
    },
    social: {
      continueWithGoogle: 'Google ile devam et',
      createWithGoogle: 'Google ile Kingshot Alliance hesabı oluştur',
      or: 'veya',
    },
    passkeys: {
      signIn: 'Geçiş anahtarıyla giriş yap',
      authenticating: 'Geçiş anahtarı kontrol ediliyor…',
    },
    confirm: {
      title: 'Kimliğini doğrula',
      description:
        'Güvenlik açısından hassas olan bu hesap değişikliğine devam etmeden önce hesabına bağlı giriş yöntemlerinden birini kullan.',
      usePassword: 'Şifre ile doğrula',
      useGoogle: 'Google ile doğrulayın',
      usePasskey: 'Geçiş anahtarıyla doğrula',
    },
  },
} satisfies MessageCatalogue;

export default messages;
