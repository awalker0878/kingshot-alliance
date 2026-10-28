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
      email: 'Surel',
    },
    register: {
      title: 'Buat akun',
      name: 'Nama',
      password: 'Kata sandi',
      passwordConfirmation: 'Konfirmasi kata sandi',
      submit: 'Buat akun',
      existingAccount: 'Sudah punya akun?',
      email: 'Surel',
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
      recoveryKicker: 'PENGUMUMAN AKUNTANSI',
      recoveryNeutral:
        'Jika ada kredensial sandi yang sah untuk surel itu, kami akan mengirimkan instruksi reset sandi yang aman.',
      checkInboxKicker: 'Periksa kotak masuk Anda',
      checkInboxHeading: 'Instruksi pemulihan diminta',
      checkInboxDescription:
        'Jika ada kredensial sandi yang sah untuk alamat yang Anda masukkan, instruksi reset sandi telah dikirim. Untuk keamanan, kami tidak bisa mengkonfirmasi apakah akun ada atau metode apa yang digunakan.',
      resetKicker: 'SECURE Password RESET',
      accountEmail: 'Email akun',
      requirements: 'Kata sandi Anda harus mencakup:',
      requirementLength: 'Setidaknya 12 karakter',
      requirementUpper: 'Huruf besar',
      requirementLower: 'Huruf kecil',
      requirementNumber: 'Sebuah angka',
      showPassword: 'Tampilkan sandi',
      hidePassword: 'Sembunyikan kata sandi',
      validationSummary: 'Lihat kembali bidang yang ditandai sebelum melanjutkan.',
    },
    verification: {
      description:
        'Kami mengirim tautan verifikasi ke alamat email di bawah ini. Verifikasi alamat sebelum melakukan tindakan akun yang dilindungi.',
      sent: 'Tautan verifikasi baru telah dikirim.',
      kicker: 'VERIFIKASI SULTAN',
      instructions: 'Buka pesan dan ikuti tautan untuk menyelesaikan verifikasi alamat surel Anda.',
      securityNote:
        'Kingshot Alliance tidak akan pernah meminta sandi Anda melalui pesan verifikasi email.',
      resendIn: 'Kau bisa mengirimkan kembali dalam {seconds} detik',
      useAnotherAccount: 'Keluar dan gunakan akun lain',
    },
    twoFactor: {
      kicker: 'Pemeriksaan keamanan',
      description: 'Masukkan kode enam digit saat ini dari aplikasi autentikatormu.',
      verifyCode: 'Verifikasi kode',
      useRecoveryCode: 'Gunakan kode pemulihan',
    },
    social: {
      continueWithGoogle: 'Lanjutkan dengan Google',
      createWithGoogle: 'Buat akun Kingshot Alliance dengan Google',
      or: 'atau',
    },
    passkeys: {
      signIn: 'Masuk dengan kunci akses',
      authenticating: 'Memeriksa kunci akses…',
    },
    confirm: {
      title: 'Konfirmasi kata sandi',
      description:
        'Gunakan salah satu metode login yang terlampir sebelum melanjutkan dengan perubahan akun sensitif ini.',
      usePassword: 'Konfirmasi dengan sandi',
      useGoogle: 'Konfirmasi dengan Google',
      usePasskey: 'Konfirmasi dengan kunci akses',
    },
  },
} satisfies MessageCatalogue;

export default messages;
