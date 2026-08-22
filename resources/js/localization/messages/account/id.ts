import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Pengingat Position Perk',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Kontrol akun',
      title: 'Akun & keamanan',
      intro:
        'Kelola identitas, verifikasi, kata sandi, autentikasi dua faktor, dan sesi aktif Anda.',
      passwordUpdated: 'Kata sandi diperbarui dan sesi terautentikasi lainnya dicabut.',
      sessionsRevoked: 'Sesi terautentikasi lainnya telah keluar.',
      twoFactorDisabled: 'Autentikasi dua faktor dinonaktifkan.',
      profileTitle: 'Profil',
      profileIntro: 'Mengubah email memerlukan verifikasi ulang.',
      timezone: 'Zona waktu',
      saveProfile: 'Simpan profil',
      emailVerification: 'Verifikasi email',
      verified: 'Terverifikasi',
      pending: 'Tertunda',
      twoFactorState: 'Autentikasi dua faktor',
      enabled: 'Aktif',
      setupPending: 'Penyiapan tertunda',
      notEnabled: 'Tidak aktif',
      twoFactorTitle: 'Autentikasi dua faktor',
      twoFactorIntro:
        'Lindungi login dengan aplikasi autentikator. Kode pemulihan hanya ditampilkan saat dibuat atau dibuat ulang.',
      startSetup: 'Mulai penyiapan',
      authenticatorSecret: 'Rahasia autentikator',
      provisioningUri: 'URI penyediaan',
      authenticationCode: 'Kode autentikasi',
      confirm: 'Konfirmasi',
      saveRecoveryCodes: 'Simpan kode pemulihan ini sekarang',
      recoveryIntro: 'Setiap kode hanya berlaku sekali. Simpan terpisah dari akun ini.',
      regenerateRecoveryCodes: 'Buat ulang kode pemulihan',
      disableTwoFactor: 'Nonaktifkan autentikasi dua faktor',
      passwordTitle: 'Ubah kata sandi',
      passwordIntro:
        'Mengubah kata sandi akan mengeluarkan perangkat lain dan menutup akses aktif lainnya.',
      currentPassword: 'Kata sandi saat ini',
      newPassword: 'Kata sandi baru',
      confirmNewPassword: 'Konfirmasi kata sandi baru',
      updatePassword: 'Perbarui kata sandi',
      sessionsTitle: 'Sesi lain',
      sessionsIntro: 'Keluar dari semua sesi terautentikasi kecuali perangkat ini.',
      signOutOthers: 'Keluar dari perangkat lain',
      dangerTitle: 'Zona berbahaya',
      deleteAccount: 'Hapus akun',
    },
    deletion: {
      eyebrow: 'Siklus akun',
      title: 'Hapus akun',
      intro:
        'Penghapusan memiliki masa tunggu tujuh hari. Kepemilikan aliansi aktif, akses administrator platform, dan penahanan hukum dapat memblokir proses. Akun yang diproses dianonimkan tanpa menghapus riwayat audit.',
      currentRequest: 'Permintaan saat ini',
      eligibleAt: 'Memenuhi syarat pada',
      requestedAt: 'Diminta',
      processedAt: 'Diproses',
      notYet: 'Belum',
      requestTitle: 'Minta penghapusan',
      requestIntro:
        'Transfer kepemilikan aliansi terlebih dahulu. Catatan yang terkena penahanan hukum atau diperlukan untuk keamanan/audit tetap disimpan dalam bentuk pseudonim.',
      requestButton: 'Minta penghapusan akun',
      confirm:
        'Minta penghapusan akun? Ada masa tunggu tujuh hari serta pemeriksaan kepemilikan dan penahanan hukum.',
      requested: 'Permintaan penghapusan akun Anda telah dicatat.',
      backToAccount: 'Kembali ke akun & keamanan',
    },
  },
} satisfies MessageCatalogue;

export default messages;
