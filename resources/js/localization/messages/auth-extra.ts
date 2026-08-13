import type { LocaleCode } from '../locales';

const en = {
  authExperience: {
    shell: {
      headline: 'Built for alliance leaders.',
      intro:
        'Secure access to the tools your alliance uses to coordinate, recruit, and prepare for what comes next.',
    },
    login: {
      intro: 'Access every alliance linked to your global account.',
      invitationNotice:
        'Sign in with the invited account to continue accepting your alliance invitation.',
      needAccount: 'Need an account?',
      register: 'Register',
    },
    register: {
      intro: 'One global identity can belong to multiple alliances.',
      invitationNotice:
        'You were invited to {alliance} as {email}. Creating your account will also accept this invitation.',
      invitationOnly:
        'Registration is currently invitation-only. Open the invitation link sent by your alliance.',
      timezone: 'Time zone',
      passwordHint: 'At least 12 characters with mixed case and a number.',
      existingAccount: 'Already have an account?',
    },
    invitation: {
      join: 'Join {alliance}',
      forEmail: 'This invitation is for {email}.',
      expires: 'Expires {date}',
      wrongAccount:
        'You are signed in as {email}. Sign in with the invited email address to accept this invitation.',
      createAndJoin: 'Create account and join',
      signInAccept: 'Sign in to accept',
    },
    password: {
      backToSignIn: 'Back to sign in',
      resetIntro: 'Resetting your password revokes personal access tokens.',
      newPassword: 'New password',
      confirmNewPassword: 'Confirm new password',
      confirmDescription:
        'This action changes alliance access or permissions, so your password must be reconfirmed.',
    },
    verification: {
      description:
        'We sent a verification link to {email}. Verify the address before performing protected account actions.',
      sent: 'A fresh verification link has been sent.',
    },
    twoFactor: {
      kicker: 'Security check',
      description: 'Enter the current six-digit code from your authenticator app.',
      verifyCode: 'Verify code',
      useRecoveryCode: 'Use recovery code',
    },
  },
} as const;

type AuthExtraTree = {
  authExperience: {
    [K in keyof typeof en.authExperience]: {
      [P in keyof (typeof en.authExperience)[K]]: string;
    };
  };
};

export const authExtraMessages: Record<LocaleCode, AuthExtraTree> = {
  en,
  ar: {
    authExperience: {
      shell: {
        headline: 'مصمم لقادة التحالفات.',
        intro: 'وصول آمن إلى الأدوات التي يستخدمها تحالفك للتنسيق والتجنيد والاستعداد لما هو قادم.',
      },
      login: {
        intro: 'ادخل إلى جميع التحالفات المرتبطة بحسابك العام.',
        invitationNotice: 'سجّل الدخول بالحساب المدعو لمتابعة قبول دعوة التحالف.',
        needAccount: 'تحتاج إلى حساب؟',
        register: 'إنشاء حساب',
      },
      register: {
        intro: 'يمكن لهوية عالمية واحدة أن تنتمي إلى عدة تحالفات.',
        invitationNotice: 'تمت دعوتك إلى {alliance} باستخدام {email}. إنشاء الحساب سيقبل هذه الدعوة أيضًا.',
        invitationOnly: 'التسجيل متاح حاليًا بالدعوة فقط. افتح رابط الدعوة الذي أرسله تحالفك.',
        timezone: 'المنطقة الزمنية',
        passwordHint: '12 حرفًا على الأقل مع أحرف كبيرة وصغيرة ورقم.',
        existingAccount: 'لديك حساب بالفعل؟',
      },
      invitation: {
        join: 'انضم إلى {alliance}',
        forEmail: 'هذه الدعوة مخصصة لـ {email}.',
        expires: 'تنتهي في {date}',
        wrongAccount: 'أنت مسجّل الدخول باسم {email}. سجّل الدخول بالبريد المدعو لقبول هذه الدعوة.',
        createAndJoin: 'أنشئ حسابًا وانضم',
        signInAccept: 'سجّل الدخول للقبول',
      },
      password: {
        backToSignIn: 'العودة إلى تسجيل الدخول',
        resetIntro: 'إعادة تعيين كلمة المرور تلغي رموز الوصول الشخصية.',
        newPassword: 'كلمة المرور الجديدة',
        confirmNewPassword: 'تأكيد كلمة المرور الجديدة',
        confirmDescription: 'هذا الإجراء يغيّر وصول التحالف أو الأذونات، لذا يجب إعادة تأكيد كلمة المرور.',
      },
      verification: {
        description: 'أرسلنا رابط تحقق إلى {email}. تحقّق من العنوان قبل تنفيذ إجراءات الحساب المحمية.',
        sent: 'تم إرسال رابط تحقق جديد.',
      },
      twoFactor: {
        kicker: 'فحص أمني',
        description: 'أدخل الرمز الحالي المكوّن من ستة أرقام من تطبيق المصادقة.',
        verifyCode: 'تحقق من الرمز',
        useRecoveryCode: 'استخدم رمز الاسترداد',
      },
    },
  },
  de: {
    authExperience: {
      shell: {
        headline: 'Für Allianzführung gemacht.',
        intro: 'Sicherer Zugriff auf die Werkzeuge, mit denen deine Allianz koordiniert, rekrutiert und sich vorbereitet.',
      },
      login: {
        intro: 'Greife auf alle Allianzen zu, die mit deinem globalen Konto verknüpft sind.',
        invitationNotice: 'Melde dich mit dem eingeladenen Konto an, um die Allianz-Einladung weiter anzunehmen.',
        needAccount: 'Du brauchst ein Konto?',
        register: 'Registrieren',
      },
      register: {
        intro: 'Eine globale Identität kann mehreren Allianzen angehören.',
        invitationNotice: 'Du wurdest als {email} zu {alliance} eingeladen. Mit der Kontoerstellung wird die Einladung ebenfalls angenommen.',
        invitationOnly: 'Die Registrierung ist derzeit nur per Einladung möglich. Öffne den Einladungslink deiner Allianz.',
        timezone: 'Zeitzone',
        passwordHint: 'Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben sowie einer Zahl.',
        existingAccount: 'Du hast bereits ein Konto?',
      },
      invitation: {
        join: '{alliance} beitreten',
        forEmail: 'Diese Einladung ist für {email}.',
        expires: 'Läuft ab: {date}',
        wrongAccount: 'Du bist als {email} angemeldet. Melde dich mit der eingeladenen E-Mail-Adresse an, um diese Einladung anzunehmen.',
        createAndJoin: 'Konto erstellen und beitreten',
        signInAccept: 'Anmelden und annehmen',
      },
      password: {
        backToSignIn: 'Zurück zur Anmeldung',
        resetIntro: 'Das Zurücksetzen deines Passworts widerruft persönliche Zugriffstoken.',
        newPassword: 'Neues Passwort',
        confirmNewPassword: 'Neues Passwort bestätigen',
        confirmDescription: 'Diese Aktion ändert Allianz-Zugriff oder Berechtigungen. Bestätige deshalb dein Passwort erneut.',
      },
      verification: {
        description: 'Wir haben einen Bestätigungslink an {email} gesendet. Bestätige die Adresse vor geschützten Kontoaktionen.',
        sent: 'Ein neuer Bestätigungslink wurde gesendet.',
      },
      twoFactor: {
        kicker: 'Sicherheitsprüfung',
        description: 'Gib den aktuellen sechsstelligen Code aus deiner Authenticator-App ein.',
        verifyCode: 'Code prüfen',
        useRecoveryCode: 'Wiederherstellungscode verwenden',
      },
    },
  },
  es: {
    authExperience: {
      shell: {
        headline: 'Hecho para líderes de alianza.',
        intro: 'Acceso seguro a las herramientas que tu alianza usa para coordinarse, reclutar y prepararse para lo que viene.',
      },
      login: {
        intro: 'Accede a todas las alianzas vinculadas a tu cuenta global.',
        invitationNotice: 'Inicia sesión con la cuenta invitada para continuar aceptando la invitación de alianza.',
        needAccount: '¿Necesitas una cuenta?',
        register: 'Registrarse',
      },
      register: {
        intro: 'Una identidad global puede pertenecer a varias alianzas.',
        invitationNotice: 'Te invitaron a {alliance} como {email}. Crear tu cuenta también aceptará esta invitación.',
        invitationOnly: 'El registro está disponible solo por invitación. Abre el enlace enviado por tu alianza.',
        timezone: 'Zona horaria',
        passwordHint: 'Al menos 12 caracteres con mayúsculas, minúsculas y un número.',
        existingAccount: '¿Ya tienes una cuenta?',
      },
      invitation: {
        join: 'Unirse a {alliance}',
        forEmail: 'Esta invitación es para {email}.',
        expires: 'Vence el {date}',
        wrongAccount: 'Has iniciado sesión como {email}. Inicia sesión con el correo invitado para aceptar esta invitación.',
        createAndJoin: 'Crear cuenta y unirse',
        signInAccept: 'Iniciar sesión para aceptar',
      },
      password: {
        backToSignIn: 'Volver a iniciar sesión',
        resetIntro: 'Restablecer tu contraseña revoca los tokens de acceso personales.',
        newPassword: 'Nueva contraseña',
        confirmNewPassword: 'Confirmar nueva contraseña',
        confirmDescription: 'Esta acción cambia el acceso o los permisos de alianza, por lo que debes confirmar de nuevo tu contraseña.',
      },
      verification: {
        description: 'Enviamos un enlace de verificación a {email}. Verifica la dirección antes de realizar acciones protegidas.',
        sent: 'Se ha enviado un nuevo enlace de verificación.',
      },
      twoFactor: {
        kicker: 'Comprobación de seguridad',
        description: 'Introduce el código actual de seis dígitos de tu aplicación de autenticación.',
        verifyCode: 'Verificar código',
        useRecoveryCode: 'Usar código de recuperación',
      },
    },
  },
  fr: {
    authExperience: {
      shell: {
        headline: 'Pensé pour les chefs d’alliance.',
        intro: 'Un accès sécurisé aux outils utilisés par votre alliance pour coordonner, recruter et préparer la suite.',
      },
      login: {
        intro: 'Accédez à toutes les alliances liées à votre compte global.',
        invitationNotice: 'Connectez-vous avec le compte invité pour poursuivre l’acceptation de l’invitation.',
        needAccount: 'Besoin d’un compte ?',
        register: 'Créer un compte',
      },
      register: {
        intro: 'Une identité globale peut appartenir à plusieurs alliances.',
        invitationNotice: 'Vous avez été invité à rejoindre {alliance} avec {email}. La création du compte acceptera aussi cette invitation.',
        invitationOnly: 'L’inscription est actuellement sur invitation uniquement. Ouvrez le lien envoyé par votre alliance.',
        timezone: 'Fuseau horaire',
        passwordHint: 'Au moins 12 caractères avec majuscules, minuscules et un chiffre.',
        existingAccount: 'Vous avez déjà un compte ?',
      },
      invitation: {
        join: 'Rejoindre {alliance}',
        forEmail: 'Cette invitation est destinée à {email}.',
        expires: 'Expire le {date}',
        wrongAccount: 'Vous êtes connecté avec {email}. Connectez-vous avec l’adresse invitée pour accepter cette invitation.',
        createAndJoin: 'Créer un compte et rejoindre',
        signInAccept: 'Se connecter pour accepter',
      },
      password: {
        backToSignIn: 'Retour à la connexion',
        resetIntro: 'La réinitialisation du mot de passe révoque les jetons d’accès personnels.',
        newPassword: 'Nouveau mot de passe',
        confirmNewPassword: 'Confirmer le nouveau mot de passe',
        confirmDescription: 'Cette action modifie l’accès ou les autorisations de l’alliance. Votre mot de passe doit donc être confirmé.',
      },
      verification: {
        description: 'Nous avons envoyé un lien de vérification à {email}. Vérifiez l’adresse avant les actions de compte protégées.',
        sent: 'Un nouveau lien de vérification a été envoyé.',
      },
      twoFactor: {
        kicker: 'Contrôle de sécurité',
        description: 'Saisissez le code actuel à six chiffres de votre application d’authentification.',
        verifyCode: 'Vérifier le code',
        useRecoveryCode: 'Utiliser un code de récupération',
      },
    },
  },
  id: {
    authExperience: {
      shell: {
        headline: 'Dibuat untuk pemimpin aliansi.',
        intro: 'Akses aman ke alat yang digunakan aliansimu untuk berkoordinasi, merekrut, dan bersiap menghadapi langkah berikutnya.',
      },
      login: {
        intro: 'Akses semua aliansi yang terhubung ke akun globalmu.',
        invitationNotice: 'Masuk dengan akun yang diundang untuk melanjutkan penerimaan undangan aliansi.',
        needAccount: 'Butuh akun?',
        register: 'Daftar',
      },
      register: {
        intro: 'Satu identitas global dapat menjadi bagian dari beberapa aliansi.',
        invitationNotice: 'Kamu diundang ke {alliance} sebagai {email}. Membuat akun juga akan menerima undangan ini.',
        invitationOnly: 'Pendaftaran saat ini hanya melalui undangan. Buka tautan undangan yang dikirim aliansimu.',
        timezone: 'Zona waktu',
        passwordHint: 'Minimal 12 karakter dengan huruf besar, huruf kecil, dan angka.',
        existingAccount: 'Sudah punya akun?',
      },
      invitation: {
        join: 'Gabung {alliance}',
        forEmail: 'Undangan ini untuk {email}.',
        expires: 'Berakhir {date}',
        wrongAccount: 'Kamu masuk sebagai {email}. Masuk dengan email yang diundang untuk menerima undangan ini.',
        createAndJoin: 'Buat akun dan gabung',
        signInAccept: 'Masuk untuk menerima',
      },
      password: {
        backToSignIn: 'Kembali ke masuk',
        resetIntro: 'Mengatur ulang kata sandi akan mencabut token akses pribadi.',
        newPassword: 'Kata sandi baru',
        confirmNewPassword: 'Konfirmasi kata sandi baru',
        confirmDescription: 'Tindakan ini mengubah akses atau izin aliansi, jadi kata sandimu harus dikonfirmasi kembali.',
      },
      verification: {
        description: 'Kami mengirim tautan verifikasi ke {email}. Verifikasi alamat sebelum melakukan tindakan akun yang dilindungi.',
        sent: 'Tautan verifikasi baru telah dikirim.',
      },
      twoFactor: {
        kicker: 'Pemeriksaan keamanan',
        description: 'Masukkan kode enam digit saat ini dari aplikasi autentikatormu.',
        verifyCode: 'Verifikasi kode',
        useRecoveryCode: 'Gunakan kode pemulihan',
      },
    },
  },
  it: {
    authExperience: {
      shell: {
        headline: 'Pensato per i leader di alleanza.',
        intro: 'Accesso sicuro agli strumenti che la tua alleanza usa per coordinarsi, reclutare e prepararsi al prossimo passo.',
      },
      login: {
        intro: 'Accedi a tutte le alleanze collegate al tuo account globale.',
        invitationNotice: 'Accedi con l’account invitato per continuare ad accettare l’invito dell’alleanza.',
        needAccount: 'Ti serve un account?',
        register: 'Registrati',
      },
      register: {
        intro: 'Una sola identità globale può appartenere a più alleanze.',
        invitationNotice: 'Sei stato invitato in {alliance} come {email}. La creazione dell’account accetterà anche questo invito.',
        invitationOnly: 'La registrazione è attualmente solo su invito. Apri il link inviato dalla tua alleanza.',
        timezone: 'Fuso orario',
        passwordHint: 'Almeno 12 caratteri con maiuscole, minuscole e un numero.',
        existingAccount: 'Hai già un account?',
      },
      invitation: {
        join: 'Unisciti a {alliance}',
        forEmail: 'Questo invito è per {email}.',
        expires: 'Scade il {date}',
        wrongAccount: 'Hai effettuato l’accesso come {email}. Accedi con l’indirizzo invitato per accettare questo invito.',
        createAndJoin: 'Crea account e unisciti',
        signInAccept: 'Accedi per accettare',
      },
      password: {
        backToSignIn: 'Torna all’accesso',
        resetIntro: 'La reimpostazione della password revoca i token di accesso personali.',
        newPassword: 'Nuova password',
        confirmNewPassword: 'Conferma nuova password',
        confirmDescription: 'Questa azione modifica accesso o autorizzazioni dell’alleanza, quindi devi confermare di nuovo la password.',
      },
      verification: {
        description: 'Abbiamo inviato un link di verifica a {email}. Verifica l’indirizzo prima delle azioni protette sull’account.',
        sent: 'È stato inviato un nuovo link di verifica.',
      },
      twoFactor: {
        kicker: 'Controllo di sicurezza',
        description: 'Inserisci il codice attuale di sei cifre dalla tua app di autenticazione.',
        verifyCode: 'Verifica codice',
        useRecoveryCode: 'Usa codice di recupero',
      },
    },
  },
  ja: {
    authExperience: {
      shell: {
        headline: '同盟リーダーのために。',
        intro: '同盟の連携、募集、次の戦いへの準備に使うツールへ安全にアクセスできます。',
      },
      login: {
        intro: 'グローバルアカウントに紐づくすべての同盟へアクセスできます。',
        invitationNotice: '同盟招待を受け入れるには、招待されたアカウントでログインしてください。',
        needAccount: 'アカウントが必要ですか？',
        register: '登録',
      },
      register: {
        intro: '1つのグローバルIDで複数の同盟に所属できます。',
        invitationNotice: '{email} として {alliance} に招待されています。アカウント作成時にこの招待も承認されます。',
        invitationOnly: '現在、登録は招待制です。同盟から送られた招待リンクを開いてください。',
        timezone: 'タイムゾーン',
        passwordHint: '大文字・小文字・数字を含む12文字以上。',
        existingAccount: 'すでにアカウントをお持ちですか？',
      },
      invitation: {
        join: '{alliance} に参加',
        forEmail: 'この招待は {email} 宛てです。',
        expires: '有効期限: {date}',
        wrongAccount: '{email} でログインしています。この招待を受け入れるには招待されたメールアドレスでログインしてください。',
        createAndJoin: 'アカウントを作成して参加',
        signInAccept: 'ログインして承認',
      },
      password: {
        backToSignIn: 'ログインに戻る',
        resetIntro: 'パスワードを再設定すると個人アクセストークンは失効します。',
        newPassword: '新しいパスワード',
        confirmNewPassword: '新しいパスワードを確認',
        confirmDescription: 'この操作は同盟へのアクセスや権限を変更するため、パスワードの再確認が必要です。',
      },
      verification: {
        description: '{email} に確認リンクを送信しました。保護されたアカウント操作の前にメールアドレスを確認してください。',
        sent: '新しい確認リンクを送信しました。',
      },
      twoFactor: {
        kicker: 'セキュリティ確認',
        description: '認証アプリに表示されている現在の6桁コードを入力してください。',
        verifyCode: 'コードを確認',
        useRecoveryCode: 'リカバリーコードを使用',
      },
    },
  },
  ko: {
    authExperience: {
      shell: {
        headline: '연맹 리더를 위해 설계했습니다.',
        intro: '연맹이 협력하고 모집하며 다음 단계를 준비하는 데 쓰는 도구에 안전하게 접속하세요.',
      },
      login: {
        intro: '글로벌 계정에 연결된 모든 연맹에 접속하세요.',
        invitationNotice: '연맹 초대를 계속 수락하려면 초대받은 계정으로 로그인하세요.',
        needAccount: '계정이 필요하신가요?',
        register: '가입',
      },
      register: {
        intro: '하나의 글로벌 계정으로 여러 연맹에 속할 수 있습니다.',
        invitationNotice: '{email} 계정으로 {alliance}에 초대되었습니다. 계정을 만들면 이 초대도 수락됩니다.',
        invitationOnly: '현재 가입은 초대 전용입니다. 연맹에서 보낸 초대 링크를 여세요.',
        timezone: '시간대',
        passwordHint: '대문자, 소문자, 숫자를 포함해 12자 이상.',
        existingAccount: '이미 계정이 있으신가요?',
      },
      invitation: {
        join: '{alliance} 가입',
        forEmail: '이 초대는 {email}용입니다.',
        expires: '만료: {date}',
        wrongAccount: '{email}로 로그인되어 있습니다. 이 초대를 수락하려면 초대받은 이메일로 로그인하세요.',
        createAndJoin: '계정 만들고 가입',
        signInAccept: '로그인하여 수락',
      },
      password: {
        backToSignIn: '로그인으로 돌아가기',
        resetIntro: '비밀번호를 재설정하면 개인 액세스 토큰이 취소됩니다.',
        newPassword: '새 비밀번호',
        confirmNewPassword: '새 비밀번호 확인',
        confirmDescription: '이 작업은 연맹 접근 또는 권한을 변경하므로 비밀번호를 다시 확인해야 합니다.',
      },
      verification: {
        description: '{email}로 인증 링크를 보냈습니다. 보호된 계정 작업 전에 이메일 주소를 인증하세요.',
        sent: '새 인증 링크를 보냈습니다.',
      },
      twoFactor: {
        kicker: '보안 확인',
        description: '인증 앱의 현재 6자리 코드를 입력하세요.',
        verifyCode: '코드 확인',
        useRecoveryCode: '복구 코드 사용',
      },
    },
  },
  pl: {
    authExperience: {
      shell: {
        headline: 'Stworzone dla liderów sojuszu.',
        intro: 'Bezpieczny dostęp do narzędzi, których sojusz używa do koordynacji, rekrutacji i przygotowań.',
      },
      login: {
        intro: 'Uzyskaj dostęp do wszystkich sojuszy połączonych z globalnym kontem.',
        invitationNotice: 'Zaloguj się na zaproszone konto, aby kontynuować przyjmowanie zaproszenia do sojuszu.',
        needAccount: 'Potrzebujesz konta?',
        register: 'Zarejestruj się',
      },
      register: {
        intro: 'Jedna globalna tożsamość może należeć do wielu sojuszy.',
        invitationNotice: 'Zaproszono Cię do {alliance} jako {email}. Utworzenie konta zaakceptuje również to zaproszenie.',
        invitationOnly: 'Rejestracja jest obecnie możliwa tylko z zaproszenia. Otwórz link wysłany przez sojusz.',
        timezone: 'Strefa czasowa',
        passwordHint: 'Co najmniej 12 znaków, w tym wielkie i małe litery oraz cyfra.',
        existingAccount: 'Masz już konto?',
      },
      invitation: {
        join: 'Dołącz do {alliance}',
        forEmail: 'To zaproszenie jest dla {email}.',
        expires: 'Wygasa: {date}',
        wrongAccount: 'Jesteś zalogowany jako {email}. Zaloguj się adresem, na który wysłano zaproszenie.',
        createAndJoin: 'Utwórz konto i dołącz',
        signInAccept: 'Zaloguj się, aby zaakceptować',
      },
      password: {
        backToSignIn: 'Wróć do logowania',
        resetIntro: 'Zresetowanie hasła unieważnia osobiste tokeny dostępu.',
        newPassword: 'Nowe hasło',
        confirmNewPassword: 'Potwierdź nowe hasło',
        confirmDescription: 'Ta czynność zmienia dostęp lub uprawnienia w sojuszu, dlatego trzeba ponownie potwierdzić hasło.',
      },
      verification: {
        description: 'Wysłaliśmy link weryfikacyjny na {email}. Zweryfikuj adres przed chronionymi czynnościami na koncie.',
        sent: 'Wysłano nowy link weryfikacyjny.',
      },
      twoFactor: {
        kicker: 'Kontrola bezpieczeństwa',
        description: 'Wpisz aktualny sześciocyfrowy kod z aplikacji uwierzytelniającej.',
        verifyCode: 'Zweryfikuj kod',
        useRecoveryCode: 'Użyj kodu odzyskiwania',
      },
    },
  },
  'pt-BR': {
    authExperience: {
      shell: {
        headline: 'Feito para líderes de aliança.',
        intro: 'Acesso seguro às ferramentas que sua aliança usa para coordenar, recrutar e se preparar para o que vem a seguir.',
      },
      login: {
        intro: 'Acesse todas as alianças vinculadas à sua conta global.',
        invitationNotice: 'Entre com a conta convidada para continuar aceitando o convite da aliança.',
        needAccount: 'Precisa de uma conta?',
        register: 'Criar conta',
      },
      register: {
        intro: 'Uma identidade global pode pertencer a várias alianças.',
        invitationNotice: 'Você foi convidado para {alliance} como {email}. Criar sua conta também aceitará este convite.',
        invitationOnly: 'O cadastro está disponível apenas por convite. Abra o link enviado pela sua aliança.',
        timezone: 'Fuso horário',
        passwordHint: 'Pelo menos 12 caracteres com maiúsculas, minúsculas e um número.',
        existingAccount: 'Já tem uma conta?',
      },
      invitation: {
        join: 'Entrar em {alliance}',
        forEmail: 'Este convite é para {email}.',
        expires: 'Expira em {date}',
        wrongAccount: 'Você está conectado como {email}. Entre com o e-mail convidado para aceitar este convite.',
        createAndJoin: 'Criar conta e entrar',
        signInAccept: 'Entrar para aceitar',
      },
      password: {
        backToSignIn: 'Voltar para entrar',
        resetIntro: 'Redefinir sua senha revoga tokens de acesso pessoais.',
        newPassword: 'Nova senha',
        confirmNewPassword: 'Confirmar nova senha',
        confirmDescription: 'Esta ação altera acesso ou permissões da aliança, então sua senha precisa ser confirmada novamente.',
      },
      verification: {
        description: 'Enviamos um link de verificação para {email}. Verifique o endereço antes de ações protegidas da conta.',
        sent: 'Um novo link de verificação foi enviado.',
      },
      twoFactor: {
        kicker: 'Verificação de segurança',
        description: 'Digite o código atual de seis dígitos do seu aplicativo autenticador.',
        verifyCode: 'Verificar código',
        useRecoveryCode: 'Usar código de recuperação',
      },
    },
  },
  ru: {
    authExperience: {
      shell: {
        headline: 'Создано для лидеров альянса.',
        intro: 'Безопасный доступ к инструментам, с которыми ваш альянс координируется, набирает игроков и готовится к следующему этапу.',
      },
      login: {
        intro: 'Получите доступ ко всем альянсам, связанным с глобальной учётной записью.',
        invitationNotice: 'Войдите в приглашённую учётную запись, чтобы продолжить принятие приглашения.',
        needAccount: 'Нужна учётная запись?',
        register: 'Зарегистрироваться',
      },
      register: {
        intro: 'Одна глобальная учётная запись может состоять в нескольких альянсах.',
        invitationNotice: 'Вас пригласили в {alliance} как {email}. Создание учётной записи также примет это приглашение.',
        invitationOnly: 'Регистрация сейчас доступна только по приглашению. Откройте ссылку от вашего альянса.',
        timezone: 'Часовой пояс',
        passwordHint: 'Не менее 12 символов, включая строчные, заглавные буквы и цифру.',
        existingAccount: 'Уже есть учётная запись?',
      },
      invitation: {
        join: 'Вступить в {alliance}',
        forEmail: 'Это приглашение предназначено для {email}.',
        expires: 'Истекает: {date}',
        wrongAccount: 'Вы вошли как {email}. Войдите с приглашённым адресом электронной почты, чтобы принять приглашение.',
        createAndJoin: 'Создать аккаунт и вступить',
        signInAccept: 'Войти и принять',
      },
      password: {
        backToSignIn: 'Вернуться ко входу',
        resetIntro: 'Сброс пароля отзывает персональные токены доступа.',
        newPassword: 'Новый пароль',
        confirmNewPassword: 'Подтвердите новый пароль',
        confirmDescription: 'Это действие меняет доступ или разрешения альянса, поэтому пароль нужно подтвердить повторно.',
      },
      verification: {
        description: 'Мы отправили ссылку подтверждения на {email}. Подтвердите адрес перед защищёнными действиями с аккаунтом.',
        sent: 'Новая ссылка подтверждения отправлена.',
      },
      twoFactor: {
        kicker: 'Проверка безопасности',
        description: 'Введите текущий шестизначный код из приложения-аутентификатора.',
        verifyCode: 'Проверить код',
        useRecoveryCode: 'Использовать код восстановления',
      },
    },
  },
  th: {
    authExperience: {
      shell: {
        headline: 'สร้างมาเพื่อผู้นำพันธมิตร',
        intro: 'เข้าถึงเครื่องมือที่พันธมิตรใช้ประสานงาน รับสมัคร และเตรียมพร้อมสำหรับสิ่งต่อไปอย่างปลอดภัย',
      },
      login: {
        intro: 'เข้าถึงพันธมิตรทั้งหมดที่เชื่อมกับบัญชีส่วนกลางของคุณ',
        invitationNotice: 'เข้าสู่ระบบด้วยบัญชีที่ได้รับเชิญเพื่อดำเนินการตอบรับคำเชิญพันธมิตรต่อ',
        needAccount: 'ต้องการบัญชีหรือไม่?',
        register: 'สมัครบัญชี',
      },
      register: {
        intro: 'ตัวตนส่วนกลางหนึ่งบัญชีสามารถอยู่ในหลายพันธมิตรได้',
        invitationNotice: 'คุณได้รับเชิญเข้า {alliance} ด้วย {email} การสร้างบัญชีจะยอมรับคำเชิญนี้ด้วย',
        invitationOnly: 'ขณะนี้สมัครได้ด้วยคำเชิญเท่านั้น โปรดเปิดลิงก์คำเชิญที่พันธมิตรส่งมา',
        timezone: 'เขตเวลา',
        passwordHint: 'อย่างน้อย 12 ตัวอักษร พร้อมตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข',
        existingAccount: 'มีบัญชีอยู่แล้ว?',
      },
      invitation: {
        join: 'เข้าร่วม {alliance}',
        forEmail: 'คำเชิญนี้สำหรับ {email}',
        expires: 'หมดอายุ {date}',
        wrongAccount: 'คุณเข้าสู่ระบบเป็น {email} โปรดเข้าสู่ระบบด้วยอีเมลที่ได้รับเชิญเพื่อยอมรับคำเชิญนี้',
        createAndJoin: 'สร้างบัญชีและเข้าร่วม',
        signInAccept: 'เข้าสู่ระบบเพื่อยอมรับ',
      },
      password: {
        backToSignIn: 'กลับไปเข้าสู่ระบบ',
        resetIntro: 'การรีเซ็ตรหัสผ่านจะยกเลิกโทเค็นการเข้าถึงส่วนบุคคล',
        newPassword: 'รหัสผ่านใหม่',
        confirmNewPassword: 'ยืนยันรหัสผ่านใหม่',
        confirmDescription: 'การดำเนินการนี้เปลี่ยนการเข้าถึงหรือสิทธิ์ของพันธมิตร จึงต้องยืนยันรหัสผ่านอีกครั้ง',
      },
      verification: {
        description: 'เราได้ส่งลิงก์ยืนยันไปที่ {email} โปรดยืนยันอีเมลก่อนดำเนินการบัญชีที่มีการป้องกัน',
        sent: 'ส่งลิงก์ยืนยันใหม่แล้ว',
      },
      twoFactor: {
        kicker: 'ตรวจสอบความปลอดภัย',
        description: 'กรอกรหัสหกหลักปัจจุบันจากแอปยืนยันตัวตนของคุณ',
        verifyCode: 'ยืนยันรหัส',
        useRecoveryCode: 'ใช้รหัสกู้คืน',
      },
    },
  },
  tr: {
    authExperience: {
      shell: {
        headline: 'İttifak liderleri için tasarlandı.',
        intro: 'İttifakınızın koordinasyon, üye alımı ve hazırlık için kullandığı araçlara güvenli erişim.',
      },
      login: {
        intro: 'Küresel hesabınıza bağlı tüm ittifaklara erişin.',
        invitationNotice: 'İttifak davetini kabul etmeye devam etmek için davet edilen hesapla giriş yapın.',
        needAccount: 'Hesaba mı ihtiyacınız var?',
        register: 'Kayıt ol',
      },
      register: {
        intro: 'Tek bir küresel kimlik birden fazla ittifaka ait olabilir.',
        invitationNotice: '{email} olarak {alliance} ittifakına davet edildiniz. Hesap oluşturmak bu daveti de kabul eder.',
        invitationOnly: 'Kayıt şu anda yalnızca davetle mümkündür. İttifakınızın gönderdiği davet bağlantısını açın.',
        timezone: 'Saat dilimi',
        passwordHint: 'Büyük harf, küçük harf ve sayı içeren en az 12 karakter.',
        existingAccount: 'Zaten hesabınız var mı?',
      },
      invitation: {
        join: '{alliance} ittifakına katıl',
        forEmail: 'Bu davet {email} içindir.',
        expires: 'Sona erme: {date}',
        wrongAccount: '{email} olarak giriş yaptınız. Bu daveti kabul etmek için davet edilen e-posta ile giriş yapın.',
        createAndJoin: 'Hesap oluştur ve katıl',
        signInAccept: 'Kabul etmek için giriş yap',
      },
      password: {
        backToSignIn: 'Girişe dön',
        resetIntro: 'Şifrenizi sıfırlamak kişisel erişim belirteçlerini iptal eder.',
        newPassword: 'Yeni şifre',
        confirmNewPassword: 'Yeni şifreyi onayla',
        confirmDescription: 'Bu işlem ittifak erişimini veya izinlerini değiştirir, bu nedenle şifrenizi yeniden doğrulamalısınız.',
      },
      verification: {
        description: '{email} adresine bir doğrulama bağlantısı gönderdik. Korumalı hesap işlemlerinden önce adresi doğrulayın.',
        sent: 'Yeni bir doğrulama bağlantısı gönderildi.',
      },
      twoFactor: {
        kicker: 'Güvenlik kontrolü',
        description: 'Kimlik doğrulama uygulamanızdaki güncel altı haneli kodu girin.',
        verifyCode: 'Kodu doğrula',
        useRecoveryCode: 'Kurtarma kodunu kullan',
      },
    },
  },
  vi: {
    authExperience: {
      shell: {
        headline: 'Được xây dựng cho lãnh đạo liên minh.',
        intro: 'Truy cập an toàn vào các công cụ liên minh dùng để phối hợp, tuyển thành viên và chuẩn bị cho bước tiếp theo.',
      },
      login: {
        intro: 'Truy cập mọi liên minh được liên kết với tài khoản toàn cục của bạn.',
        invitationNotice: 'Đăng nhập bằng tài khoản được mời để tiếp tục chấp nhận lời mời liên minh.',
        needAccount: 'Cần một tài khoản?',
        register: 'Đăng ký',
      },
      register: {
        intro: 'Một danh tính toàn cục có thể thuộc nhiều liên minh.',
        invitationNotice: 'Bạn được mời vào {alliance} với địa chỉ {email}. Tạo tài khoản cũng sẽ chấp nhận lời mời này.',
        invitationOnly: 'Hiện tại chỉ có thể đăng ký bằng lời mời. Hãy mở liên kết lời mời do liên minh gửi.',
        timezone: 'Múi giờ',
        passwordHint: 'Ít nhất 12 ký tự, có chữ hoa, chữ thường và số.',
        existingAccount: 'Đã có tài khoản?',
      },
      invitation: {
        join: 'Tham gia {alliance}',
        forEmail: 'Lời mời này dành cho {email}.',
        expires: 'Hết hạn {date}',
        wrongAccount: 'Bạn đang đăng nhập bằng {email}. Hãy đăng nhập bằng email được mời để chấp nhận lời mời này.',
        createAndJoin: 'Tạo tài khoản và tham gia',
        signInAccept: 'Đăng nhập để chấp nhận',
      },
      password: {
        backToSignIn: 'Quay lại đăng nhập',
        resetIntro: 'Đặt lại mật khẩu sẽ thu hồi các mã truy cập cá nhân.',
        newPassword: 'Mật khẩu mới',
        confirmNewPassword: 'Xác nhận mật khẩu mới',
        confirmDescription: 'Hành động này thay đổi quyền truy cập hoặc quyền hạn của liên minh, vì vậy bạn phải xác nhận lại mật khẩu.',
      },
      verification: {
        description: 'Chúng tôi đã gửi liên kết xác minh đến {email}. Hãy xác minh địa chỉ trước các thao tác tài khoản được bảo vệ.',
        sent: 'Đã gửi liên kết xác minh mới.',
      },
      twoFactor: {
        kicker: 'Kiểm tra bảo mật',
        description: 'Nhập mã sáu chữ số hiện tại từ ứng dụng xác thực của bạn.',
        verifyCode: 'Xác minh mã',
        useRecoveryCode: 'Dùng mã khôi phục',
      },
    },
  },
  'zh-CN': {
    authExperience: {
      shell: {
        headline: '为联盟领袖而打造。',
        intro: '安全访问联盟用于协调、招募和准备下一步行动的工具。',
      },
      login: {
        intro: '访问与你的全局账户关联的所有联盟。',
        invitationNotice: '请使用受邀账户登录，以继续接受联盟邀请。',
        needAccount: '需要账户？',
        register: '注册',
      },
      register: {
        intro: '一个全局身份可以加入多个联盟。',
        invitationNotice: '你以 {email} 受邀加入 {alliance}。创建账户也会接受此邀请。',
        invitationOnly: '当前仅可通过邀请注册。请打开联盟发送的邀请链接。',
        timezone: '时区',
        passwordHint: '至少12个字符，并包含大小写字母和数字。',
        existingAccount: '已有账户？',
      },
      invitation: {
        join: '加入 {alliance}',
        forEmail: '此邀请发送给 {email}。',
        expires: '到期时间：{date}',
        wrongAccount: '你当前以 {email} 登录。请使用受邀邮箱登录以接受此邀请。',
        createAndJoin: '创建账户并加入',
        signInAccept: '登录并接受',
      },
      password: {
        backToSignIn: '返回登录',
        resetIntro: '重置密码会撤销个人访问令牌。',
        newPassword: '新密码',
        confirmNewPassword: '确认新密码',
        confirmDescription: '此操作会更改联盟访问或权限，因此需要重新确认密码。',
      },
      verification: {
        description: '我们已向 {email} 发送验证链接。执行受保护的账户操作前请先验证邮箱。',
        sent: '新的验证链接已发送。',
      },
      twoFactor: {
        kicker: '安全检查',
        description: '请输入身份验证器应用中的当前六位代码。',
        verifyCode: '验证代码',
        useRecoveryCode: '使用恢复代码',
      },
    },
  },
  'zh-TW': {
    authExperience: {
      shell: {
        headline: '為聯盟領袖打造。',
        intro: '安全存取聯盟用來協調、招募與準備下一步行動的工具。',
      },
      login: {
        intro: '存取與你的全域帳戶連結的所有聯盟。',
        invitationNotice: '請使用受邀帳戶登入，以繼續接受聯盟邀請。',
        needAccount: '需要帳戶嗎？',
        register: '註冊',
      },
      register: {
        intro: '一個全域身分可以加入多個聯盟。',
        invitationNotice: '你以 {email} 受邀加入 {alliance}。建立帳戶也會接受此邀請。',
        invitationOnly: '目前僅能透過邀請註冊。請開啟聯盟傳送的邀請連結。',
        timezone: '時區',
        passwordHint: '至少12個字元，並包含大小寫字母與數字。',
        existingAccount: '已有帳戶嗎？',
      },
      invitation: {
        join: '加入 {alliance}',
        forEmail: '此邀請是給 {email}。',
        expires: '到期時間：{date}',
        wrongAccount: '你目前以 {email} 登入。請使用受邀電子郵件登入以接受此邀請。',
        createAndJoin: '建立帳戶並加入',
        signInAccept: '登入並接受',
      },
      password: {
        backToSignIn: '返回登入',
        resetIntro: '重設密碼會撤銷個人存取權杖。',
        newPassword: '新密碼',
        confirmNewPassword: '確認新密碼',
        confirmDescription: '此操作會變更聯盟存取或權限，因此需要重新確認密碼。',
      },
      verification: {
        description: '我們已將驗證連結寄到 {email}。執行受保護的帳戶操作前請先驗證電子郵件。',
        sent: '新的驗證連結已寄出。',
      },
      twoFactor: {
        kicker: '安全檢查',
        description: '請輸入驗證器應用程式目前顯示的六位數代碼。',
        verifyCode: '驗證代碼',
        useRecoveryCode: '使用復原代碼',
      },
    },
  },
};
