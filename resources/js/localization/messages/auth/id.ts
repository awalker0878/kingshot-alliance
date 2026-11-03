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
      forgotTitle: 'Pulihkan akun Anda',
      forgotDescription: 'Masukkan alamat email yang terkait dengan akun Kingshot Alliance Anda.',
      sendResetLink: 'Kirim petunjuk pemulihan',
      resetTitle: 'Pilih kata sandi baru',
      resetSubmit: 'Atur ulang kata sandi',
      confirmTitle: 'Konfirmasikan identitas Anda',
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
        'Rekrut Gubernur, siapkan Acara, kelola Aliansi Anda, dan pantau Kerajaan di satu tempat.',
    },
    login: {
      intro: 'Masuk dengan akun Anda, lalu pilih Gubernur yang ingin digunakan.',
      invitationNotice:
        'Masuk dengan akun yang diundang untuk melanjutkan penerimaan undangan aliansi.',
      needAccount: 'Butuh akun?',
      register: 'Daftar',
    },
    register: {
      intro:
        'Satu akun dapat memiliki beberapa Gubernur. Pangkat di Aliansi dan tugas di Kerajaan selalu mengikuti Gubernur yang Anda pilih.',
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
      resetIntro: 'Pilih kata sandi Kingshot Alliance yang baru untuk akun di bawah ini.',
      newPassword: 'Kata sandi baru',
      confirmNewPassword: 'Konfirmasi kata sandi baru',
      confirmDescription:
        'Tindakan ini mengubah akses terlindungi ke Aliansi. Konfirmasikan identitas Anda sebelum melanjutkan.',
      recoveryKicker: 'PEMULIHAN AKUN',
      recoveryNeutral:
        'Jika pemulihan kata sandi tersedia untuk alamat email tersebut, kami akan mengirimkan petunjuk aman untuk mengatur ulang kata sandi.',
      checkInboxKicker: 'PERIKSA KOTAK MASUK ANDA',
      checkInboxHeading: 'Petunjuk pemulihan telah diminta',
      checkInboxDescription:
        'Jika pemulihan kata sandi tersedia untuk alamat yang Anda masukkan, petunjuk pengaturan ulang kata sandi telah dikirim. Demi keamanan, kami tidak dapat mengonfirmasi apakah suatu akun ada atau metode masuk yang digunakannya.',
      resetKicker: 'ATUR ULANG KATA SANDI DENGAN AMAN',
      accountEmail: 'Email akun',
      requirements: 'Kata sandi Anda harus mencakup:',
      requirementLength: 'Setidaknya 12 karakter',
      requirementUpper: 'Huruf besar',
      requirementLower: 'Huruf kecil',
      requirementNumber: 'Satu angka',
      showPassword: 'Tampilkan sandi',
      hidePassword: 'Sembunyikan kata sandi',
      validationSummary: 'Lihat kembali bidang yang ditandai sebelum melanjutkan.',
    },
    verification: {
      description:
        'Kami telah mengirimkan tautan verifikasi yang aman untuk akun Kingshot Alliance Anda ke:',
      sent: 'Tautan verifikasi baru telah dikirim.',
      kicker: 'VERIFIKASI EMAIL',
      instructions: 'Buka pesan dan ikuti tautan untuk menyelesaikan verifikasi alamat surel Anda.',
      securityNote:
        'Kingshot Alliance tidak akan pernah meminta kata sandi Anda melalui pesan verifikasi email.',
      resendIn: 'Anda dapat mengirim ulang setelah {seconds} detik',
      useAnotherAccount: 'Keluar dan gunakan akun lain',
    },
    twoFactor: {
      kicker: 'Keamanan akun',
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
      title: 'Konfirmasikan identitas Anda',
      description:
        'Gunakan salah satu metode masuk yang terhubung ke akun Anda sebelum melanjutkan perubahan sensitif pada akun ini.',
      usePassword: 'Konfirmasi dengan sandi',
      useGoogle: 'Konfirmasi dengan Google',
      usePasskey: 'Konfirmasi dengan kunci akses',
    },
  },
} satisfies MessageCatalogue;

export default messages;
