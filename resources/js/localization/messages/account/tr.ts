import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Pozisyon Avantajı hatırlatıcıları',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Hesap yönetimi',
      title: 'Hesap ve güvenlik',
      intro:
        'Kimliğinizi, doğrulamayı, parolayı, iki faktörlü kimlik doğrulamayı ve etkin oturumları yönetin.',
      passwordUpdated: 'Parolanız güncellendi ve diğer doğrulanmış oturumlardan çıkış yapıldı.',
      sessionsRevoked: 'Diğer doğrulanmış oturumlardan çıkış yapıldı.',
      twoFactorDisabled: 'İki faktörlü kimlik doğrulama devre dışı bırakıldı.',
      profileTitle: 'Profil',
      profileIntro: 'E-posta adresini değiştirmek yeniden doğrulama gerektirir.',
      timezone: 'Saat dilimi',
      saveProfile: 'Profili kaydet',
      emailVerification: 'E-posta doğrulaması',
      verified: 'Doğrulandı',
      pending: 'Bekliyor',
      twoFactorState: 'İki faktörlü kimlik doğrulama',
      enabled: 'Etkin',
      setupPending: 'Kurulum bekliyor',
      notEnabled: 'Etkin değil',
      twoFactorTitle: 'İki faktörlü kimlik doğrulama',
      twoFactorIntro:
        'Bir doğrulayıcı uygulamayla girişi koruyun. Kurtarma kodları yalnızca oluşturulduğunda veya yenilendiğinde gösterilir.',
      startSetup: 'Kurulumu başlat',
      authenticatorSecret: 'Doğrulayıcı gizli anahtarı',
      provisioningUri: 'Yapılandırma URI’si',
      authenticationCode: 'Doğrulama kodu',
      confirm: 'Onayla',
      saveRecoveryCodes: 'Bu kurtarma kodlarını şimdi kaydedin',
      recoveryIntro: 'Her kod bir kez çalışır. Bunları bu hesaptan ayrı bir yerde saklayın.',
      regenerateRecoveryCodes: 'Kurtarma kodlarını yenile',
      disableTwoFactor: 'İki faktörlü kimlik doğrulamayı kapat',
      passwordTitle: 'Parolayı değiştir',
      passwordIntro:
        'Parolayı değiştirmek diğer cihazlardan çıkış yapar ve diğer etkin erişimleri kapatır.',
      currentPassword: 'Mevcut parola',
      newPassword: 'Yeni parola',
      confirmNewPassword: 'Yeni parolayı onayla',
      updatePassword: 'Parolayı güncelle',
      sessionsTitle: 'Diğer oturumlar',
      sessionsIntro: 'Bu cihaz dışında tüm doğrulanmış oturumlardan çıkış yapın.',
      signOutOthers: 'Diğer cihazlardan çıkış yap',
      dangerTitle: 'Tehlikeli alan',
      deleteAccount: 'Hesap silme',
    },
    deletion: {
      eyebrow: 'Hesap yaşam döngüsü',
      title: 'Hesap silme',
      intro:
        'Silme işleminde yedi günlük bekleme süresi vardır. Etkin ittifak sahipliği, platform yöneticisi erişimi ve yasal saklama işlemi engelleyebilir. İşlenen hesaplar denetim geçmişi silinmeden anonimleştirilir.',
      currentRequest: 'Mevcut istek',
      status: 'Durum',
      eligibleAt: 'Uygun tarih',
      requestedAt: 'İstek tarihi',
      processedAt: 'İşlendi',
      notYet: 'Henüz değil',
      requestTitle: 'Silme isteği',
      requestIntro:
        'Önce sahip olduğunuz ittifakların sahipliğini devredin. Yasal saklama kapsamındaki veya güvenlik/denetim için gerekli kayıtlar takma adlı biçimde korunur.',
      requestButton: 'Hesap silme isteği',
      confirm:
        'Hesap silme isteği gönderilsin mi? Yedi günlük bekleme süresi ile sahiplik/yasal saklama kontrolleri uygulanır.',
      requested: 'Hesap silme isteğiniz kaydedildi.',
      backToAccount: 'Hesap ve güvenliğe dön',
    },
  },
} satisfies MessageCatalogue;

export default messages;
