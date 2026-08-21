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
    playerAlliance: 'Aliansi Gubernur aktif',
    noPlayerAlliance: 'Gubernur aktif saat ini tidak berada dalam Aliansi.',
    skipToContent: 'Lewati ke konten',
  },
  navigation: {
    home: 'Beranda',
    dashboard: 'Ringkasan Aliansi',
    alliance: 'Aliansi',
    events: 'Event',
    roster: 'Anggota Aliansi',
    recruitment: 'Rekrutmen',
    content: 'Papan Pengumuman',
    contributions: 'Kontribusi Aliansi',
    kingdom: 'Aliansi Kingdom',
    transfers: 'Transfer Kingdom',
    integrations: 'Koneksi',
    profile: 'Akun Gubernur',
    settings: 'Pengaturan',
    allianceOperations: 'Aliansi',
    kingdomOperations: 'Kingdom',
    account: 'Akun Gubernur',
  },
  application: {
    dashboard: {
      title: 'Ringkasan Aliansi',
      eyebrow: 'Aliansi Anda',
      welcome: 'Selamat datang, Gubernur {name}',
      verificationPending: 'Verifikasi email tertunda',
      playerContextTitle: 'Gubernur aktif',
      playerContextIntro:
        'Ganti Gubernur untuk mengubah identitas Kingshot yang digunakan untuk tindakan Aliansi dan Kingdom.',
      playerKingdom: 'Kingdom #{kingdom}',
      playerAuthorityIntro:
        'Peringkat Aliansi, peran, tugas Kingdom, dan akses Event mengikuti Gubernur aktif.',
      selectPlayer: 'Pilih Gubernur',
      playerAllianceTitle: 'Aliansi Gubernur aktif',
      playerAllianceIntro: 'Akses Aliansi mengikuti peringkat dan peran Gubernur aktif.',
      noPlayerAllianceTitle: 'Gubernur ini tidak berada dalam Aliansi',
      noPlayerAllianceIntro:
        'Ganti Gubernur, bergabung dengan Aliansi, atau buat Aliansi untuk menggunakan fitur Aliansi.',
      openPlayerAlliance: 'Buka Aliansi',
      active: 'Aktif',
      roles: 'Peran Aliansi',
      kingdomAlliances: 'Aliansi Kingdom',
      transfers: 'Transfer Kingdom',
      kingdomSettings: 'Pengaturan Kingdom',
      createTitle: 'Buat Aliansi',
      createIntro:
        'Buat Aliansi untuk Gubernur aktif. Aliansi menggunakan Kingdom Gubernur tersebut, dan Gubernur pendiri menjadi R5.',
      allianceName: 'Nama Aliansi',
      timezone: 'Zona waktu Aliansi',
      create: 'Buat Aliansi',
    },
  },
} satisfies MessageCatalogue;

export default messages;
