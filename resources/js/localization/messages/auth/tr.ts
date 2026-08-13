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
    },
    verification: {
      description:
        '{email} adresine bir doğrulama bağlantısı gönderdik. Korumalı hesap işlemlerinden önce adresi doğrulayın.',
      sent: 'Yeni bir doğrulama bağlantısı gönderildi.',
    },
    twoFactor: {
      kicker: 'Güvenlik kontrolü',
      description: 'Kimlik doğrulama uygulamanızdaki güncel altı haneli kodu girin.',
      verifyCode: 'Kodu doğrula',
      useRecoveryCode: 'Kurtarma kodunu kullan',
    },
  },
} satisfies MessageCatalogue;

export default messages;
