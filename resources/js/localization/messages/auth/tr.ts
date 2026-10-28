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
      forgotTitle: 'Şifrenizi sıfırlayın',
      forgotDescription: 'E-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.',
      sendResetLink: 'Sıfırlama bağlantısı gönder',
      resetTitle: 'Yeni bir şifre seçin',
      resetSubmit: 'Şifreyi sıfırla',
      confirmTitle: 'Şifrenizi onaylayın',
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
        'İttifakınızın koordinasyon, üye alımı ve hazırlık için kullandığı araçlara güvenli erişim.',
    },
    login: {
      intro: 'Küresel hesabınıza bağlı tüm ittifaklara erişin.',
      invitationNotice:
        'İttifak davetini kabul etmeye devam etmek için davet edilen hesapla giriş yapın.',
      needAccount: 'Hesaba mı ihtiyacınız var?',
      register: 'Kayıt ol',
    },
    register: {
      intro: 'Tek bir küresel kimlik birden fazla ittifaka ait olabilir.',
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
      resetIntro: 'Şifrenizi sıfırlamak kişisel erişim belirteçlerini iptal eder.',
      newPassword: 'Yeni şifre',
      confirmNewPassword: 'Yeni şifreyi onayla',
      confirmDescription:
        'Bu işlem ittifak erişimini veya izinlerini değiştirir, bu nedenle şifrenizi yeniden doğrulamalısınız.',
      recoveryKicker: 'Hesap Geri Alımı',
      recoveryNeutral:
        'Eğer bu e-posta için geçerli bir şifre kimlik bilgisi varsa, güvenli bir şifre geri yükleme talimatı göndereceğiz.',
      checkInboxKicker: 'Poştunu kontrol et.',
      checkInboxHeading: 'Geri alma talimatları isteniyor',
      checkInboxDescription:
        'Giriş yaptığınız adres için geçerli bir şifre kimlik bilgisi mevcutsa, şifre yeniden başlatma talimatları gönderildi. Güvenlik açısından, bir hesabın var olup olmadığını veya hangi yöntemleri kullandığını doğrulayamayız.',
      resetKicker: 'GÜVENLİ ŞİFRE AÇIKLAMASI',
      accountEmail: 'Hesap e-posta',
      requirements: 'Şifreniz şunu içermelidir:',
      requirementLength: 'En az 12 karakter',
      requirementUpper: 'Büyük harf',
      requirementLower: 'Küçük harf',
      requirementNumber: 'Bir sayı.',
      showPassword: 'Şifreyi göster',
      hidePassword: 'Şifreyi Gizle',
      validationSummary: 'Devam etmeden önce işaretlenen alanları inceleyin.',
    },
    verification: {
      description:
        'Aşağıdaki e-posta adresine bir doğrulama bağlantısı gönderdik. Korumalı hesap işlemlerinden önce adresi doğrulayın.',
      sent: 'Yeni bir doğrulama bağlantısı gönderildi.',
      kicker: 'E-MAIL TESTİ',
      instructions: 'E-posta adresinizi doğrulamak için mesajı açın ve bağlantıyı izleyin.',
      securityNote:
        'Kingshot Alliance e-posta doğrulama mesajı yoluyla asla şifrenizi sormayacaktır.',
      resendIn: '{seconds} saniye içinde tekrar gönderebilirsin.',
      useAnotherAccount: 'Çık ve başka bir hesap kullan',
    },
    twoFactor: {
      kicker: 'Güvenlik kontrolü',
      description: 'Kimlik doğrulama uygulamanızdaki güncel altı haneli kodu girin.',
      verifyCode: 'Kodu doğrula',
      useRecoveryCode: 'Kurtarma kodunu kullan',
    },
    social: {
      continueWithGoogle: 'Google ile devam et.',
      createWithGoogle: 'Google ile Kingshot Alliance hesabı oluştur',
      or: 'veya',
    },
    passkeys: {
      signIn: 'Geçiş anahtarıyla giriş yap',
      authenticating: 'Geçiş anahtarı kontrol ediliyor…',
    },
    confirm: {
      title: 'Şifrenizi onaylayın',
      description:
        'Bu hassas hesap değişikliğine devam etmeden önce bağlanmış olan giriş yöntemlerinizden birini kullanın.',
      usePassword: 'Şifre ile doğrula',
      useGoogle: 'Google ile doğrulayın',
      usePasskey: 'Geçiş anahtarıyla doğrula',
    },
  },
} satisfies MessageCatalogue;

export default messages;
