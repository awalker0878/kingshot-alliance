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
      forgotTitle: 'パスワードをリセット',
      forgotDescription: 'メールアドレスを入力すると、パスワード再設定リンクを送信します。',
      sendResetLink: '再設定リンクを送信',
      resetTitle: '新しいパスワードを選択',
      resetSubmit: 'パスワードをリセット',
      confirmTitle: 'パスワードを確認',
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
      resetIntro: 'パスワードを再設定すると個人アクセストークンは失効します。',
      newPassword: '新しいパスワード',
      confirmNewPassword: '新しいパスワードを確認',
      confirmDescription:
        'この操作は同盟へのアクセスや権限を変更するため、パスワードの再確認が必要です。',
    },
    verification: {
      description:
        '{email} に確認リンクを送信しました。保護されたアカウント操作の前にメールアドレスを確認してください。',
      sent: '新しい確認リンクを送信しました。',
    },
    twoFactor: {
      kicker: 'セキュリティ確認',
      description: '認証アプリに表示されている現在の6桁コードを入力してください。',
      verifyCode: 'コードを確認',
      useRecoveryCode: 'リカバリーコードを使用',
    },
  },
} satisfies MessageCatalogue;

export default messages;
