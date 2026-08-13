import type { MessageCatalogue } from '../../types';

const messages = {
  accountExperience: {
    account: {
      eyebrow: 'アカウント管理',
      title: 'アカウントとセキュリティ',
      intro: '本人情報、メール認証、パスワード、二要素認証、アクティブなセッションを管理します。',
      passwordUpdated: 'パスワードを更新し、他の認証済みセッションを無効にしました。',
      sessionsRevoked: '他の認証済みセッションからサインアウトしました。',
      twoFactorDisabled: '二要素認証を無効にしました。',
      profileTitle: 'プロフィール',
      profileIntro: 'メールアドレスを変更すると再認証が必要です。',
      timezone: 'タイムゾーン',
      saveProfile: 'プロフィールを保存',
      emailVerification: 'メール認証',
      verified: '認証済み',
      pending: '保留中',
      twoFactorState: '二要素認証',
      enabled: '有効',
      setupPending: '設定保留中',
      notEnabled: '無効',
      twoFactorTitle: '二要素認証',
      twoFactorIntro:
        'TOTP認証アプリでサインインを保護します。リカバリーコードは作成または再生成時のみ表示されます。',
      startSetup: '設定を開始',
      authenticatorSecret: '認証アプリのシークレット',
      provisioningUri: 'プロビジョニングURI',
      authenticationCode: '認証コード',
      confirm: '確認',
      saveRecoveryCodes: 'リカバリーコードを今すぐ保存',
      recoveryIntro:
        '各コードは1回だけ使用できます。このアカウントとは別の場所に保管してください。',
      regenerateRecoveryCodes: 'リカバリーコードを再生成',
      disableTwoFactor: '二要素認証を無効化',
      passwordTitle: 'パスワード変更',
      passwordIntro:
        'パスワードを変更すると個人アクセストークンが失効し、他の認証済みセッションも無効になります。',
      currentPassword: '現在のパスワード',
      newPassword: '新しいパスワード',
      confirmNewPassword: '新しいパスワードを確認',
      updatePassword: 'パスワードを更新',
      sessionsTitle: 'その他のセッション',
      sessionsIntro: 'この端末以外の認証済みセッションをすべて無効にします。',
      signOutOthers: '他の端末からサインアウト',
      dangerTitle: '危険な操作',
      deleteAccount: 'アカウント削除',
    },
    deletion: {
      eyebrow: 'アカウントライフサイクル',
      title: 'アカウント削除',
      intro:
        '削除には7日間のクーリングオフ期間があります。アクティブな同盟所有権、プラットフォーム管理者権限、法的保留により処理が停止する場合があります。処理済みアカウントは監査履歴を消さず匿名化されます。',
      currentRequest: '現在の申請',
      status: '状態',
      eligibleAt: '処理可能日時',
      requestedAt: '申請日時',
      processedAt: '処理日時',
      notYet: '未処理',
      requestTitle: '削除を申請',
      requestIntro:
        '所有する同盟の所有権を先に移してください。法的保留またはセキュリティ・監査に必要な記録は仮名化して保持されます。',
      requestButton: 'アカウント削除を申請',
      confirm: 'アカウント削除を申請しますか？7日間の待機期間と所有権・法的保留の確認があります。',
      requested: 'アカウント削除申請を記録しました。',
      backToAccount: 'アカウントとセキュリティに戻る',
    },
  },
} satisfies MessageCatalogue;

export default messages;
