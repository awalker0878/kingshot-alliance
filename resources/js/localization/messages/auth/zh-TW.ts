import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: '登入',
      email: '電子郵件',
      password: '密碼',
      remember: '記住我',
      forgotPassword: '忘記密碼？',
      submit: '登入',
      createAccount: '建立帳戶',
      invitation: '有邀請嗎？',
    },
    register: {
      title: '建立帳戶',
      name: '名稱',
      email: '電子郵件',
      password: '密碼',
      passwordConfirmation: '確認密碼',
      submit: '建立帳戶',
      existingAccount: '已有帳戶？',
    },
    password: {
      forgotTitle: '重設密碼',
      forgotDescription: '輸入你的電子郵件，我們會寄送密碼重設連結。',
      sendResetLink: '寄送重設連結',
      resetTitle: '設定新密碼',
      resetSubmit: '重設密碼',
      confirmTitle: '確認密碼',
    },
    verification: {
      title: '驗證電子郵件',
      resend: '重新寄送驗證郵件',
    },
    twoFactor: {
      title: '雙重驗證',
      code: '驗證碼',
      recoveryCode: '復原代碼',
      submit: '繼續',
    },
    invitation: {
      title: '聯盟邀請',
      accept: '接受邀請',
    },
  },
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
} satisfies MessageCatalogue;

export default messages;
