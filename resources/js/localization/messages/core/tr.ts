import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Dil',
    signIn: 'Giriş yap',
    signOut: 'Çıkış yap',
    createAccount: 'Hesap oluştur',
    continue: 'Devam et',
    cancel: 'İptal',
    save: 'Kaydet',
    close: 'Kapat',
    loading: 'Yükleniyor',
    menu: 'Menü',
    openNavigation: 'Navigasyonu aç',
    closeNavigation: 'Navigasyonu kapat',
    playerAlliance: 'Etkin oyuncunun ittifakı',
    noPlayerAlliance: 'Etkin oyuncunun aktif bir ittifak üyeliği yok.',
    skipToContent: 'İçeriğe atla',
  },
  navigation: {
    home: 'Ana sayfa',
    dashboard: 'Panel',
    alliance: 'İttifak',
    events: 'Etkinlikler',
    roster: 'Üyeler',
    recruitment: 'Üye alımı',
    content: 'İçerik',
    contributions: 'Katkılar',
    kingdom: 'Krallık',
    transfers: 'Transferler',
    integrations: 'Entegrasyonlar',
    profile: 'Profil',
    settings: 'Ayarlar',
    allianceOperations: 'İttifak işlemleri',
    kingdomOperations: 'Krallık işlemleri',
    account: 'Hesap',
  },
  application: {
    dashboard: {
      title: 'Kontrol paneli',
      eyebrow: 'İttifak komutası',
      welcome: 'Hoş geldin, {name}',
      verificationPending: 'E-posta doğrulaması bekleniyor',
      playerContextTitle: 'Etkin oyuncu',
      playerContextIntro:
        'Oyuncu değiştirmek, ittifak ve krallık yetkileri için kullanılan oyun kimliğini değiştirir.',
      playerKingdom: 'Krallık #{kingdom}',
      playerAuthorityIntro:
        'İttifak üyeliği, rütbe, roller, krallık izinleri ve oyun eylemleri yalnızca bu oyuncudan belirlenir.',
      selectPlayer: 'Vali seç',
      playerAllianceTitle: 'Etkin oyuncunun ittifakı',
      playerAllianceIntro:
        'İttifak araçları yalnızca etkin oyuncunun üyeliğini, rütbesini ve rollerini kullanır.',
      noPlayerAllianceTitle: 'Bu oyuncu bir ittifakta değil',
      noPlayerAllianceIntro:
        'İttifak araçlarını açmadan önce oyuncu değiştirin veya etkin oyuncuyla bir ittifak kurun/katılın.',
      openPlayerAlliance: 'Oyuncunun ittifakını aç',
      active: 'Etkin',
      roles: 'Roller',
      roster: 'Kadro',
      kingdomAlliances: 'Krallık ittifakları',
      transfers: 'Transferler',
      kingdomSettings: 'Krallık ayarları',
      createTitle: 'İttifak oluştur',
      createIntro:
        'Etkin oyuncu için yeni bir ittifak kurun. İttifakın krallığı bu oyuncudan belirlenir ve oyuncu ilk R5 olur.',
      allianceName: 'İttifak adı',
      timezone: 'Saat dilimi',
      create: 'İttifak oluştur',
    },
  },
} satisfies MessageCatalogue;

export default messages;
