import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'ログイン',
      email: 'メールアドレス',
      password: 'パスワード',
      remember: 'ログイン状態を保持',
      forgotPassword: 'パスワードを忘れましたか？',
      submit: 'ログイン',
      createAccount: 'アカウント作成',
      invitation: '招待をお持ちですか？',
    },
    register: {
      title: 'アカウント作成',
      name: '名前',
      email: 'メールアドレス',
      password: 'パスワード',
      passwordConfirmation: 'パスワード確認',
      submit: 'アカウント作成',
      existingAccount: 'すでにアカウントをお持ちですか？',
    },
    password: {
      forgotTitle: 'アカウントを復旧',
      forgotDescription: 'Kingshot Allianceアカウントに登録したメールアドレスを入力してください。',
      sendResetLink: '復旧手順を送信',
      resetTitle: '新しいパスワードを選択',
      resetSubmit: 'パスワードをリセット',
      confirmTitle: '本人確認',
    },
    verification: {
      title: 'メールアドレスを確認',
      resend: '確認メールを再送',
    },
    twoFactor: {
      title: '二要素認証',
      code: '認証コード',
      recoveryCode: 'リカバリーコード',
      submit: '続ける',
    },
    invitation: {
      title: '同盟招待',
      accept: '招待を承認',
    },
  },
  authExperience: {
    shell: {
      headline: '同盟リーダーのために。',
      intro: '総督の募集、イベントの準備、同盟の管理、王国の状況確認を一か所で行えます。',
    },
    login: {
      intro: 'アカウントにログインしてから、使用する総督を選択してください。',
      invitationNotice: '同盟招待を受け入れるには、招待されたアカウントでログインしてください。',
      needAccount: 'アカウントが必要ですか？',
      register: '登録',
    },
    register: {
      intro:
        '一つのアカウントで複数の総督を所有できます。同盟内のランクと王国での役割は、常に選択した総督に紐づきます。',
      invitationNotice:
        '{email} として {alliance} に招待されています。アカウント作成時にこの招待も承認されます。',
      invitationOnly: '現在、登録は招待制です。同盟から送られた招待リンクを開いてください。',
      timezone: 'タイムゾーン',
      passwordHint: '大文字・小文字・数字を含む12文字以上。',
      existingAccount: 'すでにアカウントをお持ちですか？',
    },
    invitation: {
      join: '{alliance} に参加',
      forEmail: 'この招待は {email} 宛てです。',
      expires: '有効期限: {date}',
      wrongAccount:
        '{email} でログインしています。この招待を受け入れるには招待されたメールアドレスでログインしてください。',
      createAndJoin: 'アカウントを作成して参加',
      signInAccept: 'ログインして承認',
    },
    password: {
      backToSignIn: 'ログインに戻る',
      resetIntro:
        '以下のアカウントに使用する新しいKingshot Allianceのパスワードを設定してください。',
      newPassword: '新しいパスワード',
      confirmNewPassword: '新しいパスワードを確認',
      confirmDescription:
        'この操作は同盟への保護されたアクセスを変更します。続行する前に本人確認を行ってください。',
      recoveryKicker: 'アカウントの復旧',
      recoveryNeutral:
        'このメールアドレスでパスワードの復旧が利用できる場合、安全に再設定するための手順を送信します。',
      checkInboxKicker: '受信トレイを確認',
      checkInboxHeading: '復旧手順の送信をリクエストしました',
      checkInboxDescription:
        '入力したメールアドレスでパスワードの復旧が利用できる場合、再設定の手順を送信しました。セキュリティ上の理由から、アカウントの有無や使用しているログイン方法はお伝えできません。',
      resetKicker: '安全なパスワード再設定',
      accountEmail: 'アカウントのメールアドレス',
      requirements: 'パスワードには次の内容が含まれなければなりません:',
      requirementLength: '12 文字以上',
      requirementUpper: '大文字',
      requirementLower: '小文字',
      requirementNumber: '数字',
      showPassword: 'パスワードを表示',
      hidePassword: 'パスワードを隠す',
      validationSummary: '続行する前に、強調表示されたフィールドを確認してください。',
    },
    verification: {
      description:
        'Kingshot Allianceアカウントの安全な確認リンクを次のメールアドレスに送信しました：',
      sent: '新しい確認リンクを送信しました。',
      kicker: 'メールアドレスの確認',
      instructions: 'メッセージを開き、リンクに従ってメールアドレスの確認を完了します。',
      securityNote:
        'Kingshot Allianceがメールアドレスの確認メッセージでパスワードをお尋ねすることはありません。',
      resendIn: '{seconds}秒後に再送信できます',
      useAnotherAccount: 'ログアウトして他のアカウントを使う',
    },
    twoFactor: {
      kicker: 'アカウントのセキュリティ',
      description: '認証アプリに表示されている現在の6桁コードを入力してください。',
      verifyCode: 'コードを確認',
      useRecoveryCode: 'リカバリーコードを使用',
    },
    social: {
      continueWithGoogle: 'Googleで続行',
      createWithGoogle: 'GoogleでKingshot Allianceアカウントを作成',
      or: 'または',
    },
    passkeys: {
      signIn: 'パスキーでログイン',
      authenticating: 'パスキーを確認中…',
    },
    confirm: {
      title: '本人確認',
      description:
        'この重要なアカウント変更を続ける前に、アカウントに紐づくログイン方法のいずれかで本人確認を行ってください。',
      usePassword: 'パスワードで確認',
      useGoogle: 'Google で確認',
      usePasskey: 'パスキーで確認',
    },
  },
} satisfies MessageCatalogue;

export default messages;
