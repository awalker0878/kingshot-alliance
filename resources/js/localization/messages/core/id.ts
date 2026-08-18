import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Bahasa',
    signIn: 'Masuk',
    signOut: 'Keluar',
    createAccount: 'Buat akun',
    continue: 'Lanjutkan',
    cancel: 'Batal',
    save: 'Simpan',
    close: 'Tutup',
    loading: 'Memuat',
    openNavigation: 'Buka navigasi',
    closeNavigation: 'Tutup navigasi',
    playerAlliance: 'Aliansi pemain aktif',
    noPlayerAlliance: 'Pemain aktif tidak memiliki keanggotaan aliansi yang aktif.',
    skipToContent: 'Lewati ke konten',
  },
  navigation: {
    home: 'Beranda',
    dashboard: 'Dasbor',
    alliance: 'Aliansi',
    events: 'Acara',
    roster: 'Anggota',
    recruitment: 'Rekrutmen',
    content: 'Konten',
    contributions: 'Kontribusi',
    kingdom: 'Kerajaan',
    transfers: 'Transfer',
    integrations: 'Integrasi',
    profile: 'Profil',
    settings: 'Pengaturan',
    allianceOperations: 'Operasi aliansi',
    kingdomOperations: 'Operasi kerajaan',
    account: 'Akun',
  },
  application: {
    dashboard: {
      title: 'Dasbor',
      eyebrow: 'Komando aliansi',
      welcome: 'Selamat datang, {name}',
      verificationPending: 'Verifikasi email tertunda',
      playerContextTitle: 'Pemain aktif',
      playerContextIntro:
        'Mengganti pemain mengubah identitas game yang digunakan untuk otoritas aliansi dan kingdom.',
      playerKingdom: 'Kingdom #{kingdom}',
      playerAuthorityIntro:
        'Keanggotaan, peringkat, peran, izin kingdom, dan tindakan game ditentukan hanya dari pemain ini.',
      selectPlayer: 'Pilih Gubernur',
      playerAllianceTitle: 'Aliansi pemain aktif',
      playerAllianceIntro:
        'Alat aliansi hanya menggunakan keanggotaan, peringkat, dan peran pemain aktif.',
      noPlayerAllianceTitle: 'Pemain ini tidak berada dalam aliansi',
      noPlayerAllianceIntro:
        'Ganti pemain atau buat/gabung aliansi dengan pemain aktif sebelum membuka alat aliansi.',
      openPlayerAlliance: 'Buka aliansi pemain',
      active: 'Aktif',
      roles: 'Peran',
      kingdomAlliances: 'Aliansi kerajaan',
      transfers: 'Transfer',
      kingdomSettings: 'Pengaturan kerajaan',
      createTitle: 'Buat aliansi',
      createIntro:
        'Buat aliansi untuk pemain aktif. Kingdom aliansi diturunkan dari pemain tersebut, yang menjadi R5 pertama.',
      allianceName: 'Nama aliansi',
      timezone: 'Zona waktu',
      create: 'Buat aliansi',
    },
  },
} satisfies MessageCatalogue;

export default messages;
