import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Masuk',
      password: 'Kata sandi',
      remember: 'Ingat saya',
      forgotPassword: 'Lupa kata sandi?',
      submit: 'Masuk',
      createAccount: 'Buat akun',
      invitation: 'Punya undangan?',
    },
    register: {
      title: 'Buat akun',
      name: 'Nama',
      password: 'Kata sandi',
      passwordConfirmation: 'Konfirmasi kata sandi',
      submit: 'Buat akun',
      existingAccount: 'Sudah punya akun?',
    },
    password: {
      forgotTitle: 'Atur ulang kata sandi',
      forgotDescription:
        'Masukkan alamat email dan kami akan mengirim tautan untuk mengatur ulang kata sandi.',
      sendResetLink: 'Kirim tautan atur ulang',
      resetTitle: 'Pilih kata sandi baru',
      resetSubmit: 'Atur ulang kata sandi',
      confirmTitle: 'Konfirmasi kata sandi',
    },
    verification: {
      title: 'Verifikasi email Anda',
      resend: 'Kirim ulang email verifikasi',
    },
    twoFactor: {
      title: 'Autentikasi dua faktor',
      code: 'Kode autentikasi',
      recoveryCode: 'Kode pemulihan',
      submit: 'Lanjutkan',
    },
    invitation: {
      title: 'Undangan aliansi',
      accept: 'Terima undangan',
    },
  },
  authExperience: {
    shell: {
      headline: 'Dibuat untuk pemimpin aliansi.',
      intro:
        'Akses aman ke alat yang digunakan aliansimu untuk berkoordinasi, merekrut, dan bersiap menghadapi langkah berikutnya.',
    },
    login: {
      intro: 'Akses semua aliansi yang terhubung ke akun globalmu.',
      invitationNotice:
        'Masuk dengan akun yang diundang untuk melanjutkan penerimaan undangan aliansi.',
      needAccount: 'Butuh akun?',
      register: 'Daftar',
    },
    register: {
      intro: 'Satu identitas global dapat menjadi bagian dari beberapa aliansi.',
      invitationNotice:
        'Kamu diundang ke {alliance} sebagai {email}. Membuat akun juga akan menerima undangan ini.',
      invitationOnly:
        'Pendaftaran saat ini hanya melalui undangan. Buka tautan undangan yang dikirim aliansimu.',
      timezone: 'Zona waktu',
      passwordHint: 'Minimal 12 karakter dengan huruf besar, huruf kecil, dan angka.',
      existingAccount: 'Sudah punya akun?',
    },
    invitation: {
      join: 'Gabung {alliance}',
      forEmail: 'Undangan ini untuk {email}.',
      expires: 'Berakhir {date}',
      wrongAccount:
        'Kamu masuk sebagai {email}. Masuk dengan email yang diundang untuk menerima undangan ini.',
      createAndJoin: 'Buat akun dan gabung',
      signInAccept: 'Masuk untuk menerima',
    },
    password: {
      backToSignIn: 'Kembali ke masuk',
      resetIntro: 'Mengatur ulang kata sandi akan mencabut token akses pribadi.',
      newPassword: 'Kata sandi baru',
      confirmNewPassword: 'Konfirmasi kata sandi baru',
      confirmDescription:
        'Tindakan ini mengubah akses atau izin aliansi, jadi kata sandimu harus dikonfirmasi kembali.',
    },
    verification: {
      description:
        'Kami mengirim tautan verifikasi ke {email}. Verifikasi alamat sebelum melakukan tindakan akun yang dilindungi.',
      sent: 'Tautan verifikasi baru telah dikirim.',
    },
    twoFactor: {
      kicker: 'Pemeriksaan keamanan',
      description: 'Masukkan kode enam digit saat ini dari aplikasi autentikatormu.',
      verifyCode: 'Verifikasi kode',
      useRecoveryCode: 'Gunakan kode pemulihan',
    },
  },
} satisfies MessageCatalogue;

export default messages;
