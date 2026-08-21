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
    playerAlliance: 'Etkin Valinin İttifakı',
    noPlayerAlliance: 'Etkin Vali şu anda bir İttifakta değil.',
    skipToContent: 'İçeriğe atla',
  },
  navigation: {
    home: 'Ana sayfa',
    dashboard: 'İttifak özeti',
    alliance: 'İttifak',
    events: 'Etkinlikler',
    roster: 'İttifak üyeleri',
    recruitment: 'Üye alımı',
    content: 'Duyuru panosu',
    contributions: 'İttifak katkıları',
    kingdom: 'Krallık İttifakları',
    transfers: 'Krallık Transferi',
    integrations: 'Bağlantılar',
    profile: 'Vali hesabı',
    settings: 'Ayarlar',
    allianceOperations: 'İttifak',
    kingdomOperations: 'Krallık',
    account: 'Vali hesabı',
  },
  application: {
    dashboard: {
      title: 'İttifak özeti',
      eyebrow: 'İttifakın',
      welcome: 'Hoş geldin, Vali {name}',
      verificationPending: 'E-posta doğrulaması bekleniyor',
      playerContextTitle: 'Etkin Vali',
      playerContextIntro:
        'İttifak ve Krallık işlemlerinde kullanılan Kingshot kimliğini değiştirmek için Vali değiştir.',
      playerKingdom: 'Krallık #{kingdom}',
      playerAuthorityIntro:
        'İttifak rütbesi, roller, Krallık görevleri ve Etkinlik erişimi etkin Valiyi takip eder.',
      selectPlayer: 'Vali seç',
      playerAllianceTitle: 'Etkin Valinin İttifakı',
      playerAllianceIntro: 'İttifak erişimi etkin Valinin rütbe ve rollerini takip eder.',
      noPlayerAllianceTitle: 'Bu Vali bir İttifakta değil',
      noPlayerAllianceIntro:
        'İttifak özelliklerini kullanmak için Vali değiştir, bir İttifaka katıl veya bir İttifak oluştur.',
      openPlayerAlliance: 'İttifakı aç',
      active: 'Etkin',
      roles: 'İttifak rolleri',
      roster: 'İttifak üyeleri',
      kingdomAlliances: 'Krallık İttifakları',
      transfers: 'Krallık Transferi',
      kingdomSettings: 'Krallık ayarları',
      createTitle: 'İttifak oluştur',
      createIntro:
        'Etkin Vali için bir İttifak oluştur. İttifak bu Valinin Krallığını kullanır ve kurucu Vali R5 olur.',
      allianceName: 'İttifak adı',
      timezone: 'İttifak saat dilimi',
      create: 'İttifak oluştur',
    },
  },
} satisfies MessageCatalogue;

export default messages;
