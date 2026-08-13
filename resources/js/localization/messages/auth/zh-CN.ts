import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: '登录',
      email: '电子邮箱',
      password: '密码',
      remember: '记住我',
      forgotPassword: '忘记密码？',
      submit: '登录',
      createAccount: '创建账户',
      invitation: '有邀请吗？',
    },
    register: {
      title: '创建账户',
      name: '名称',
      email: '电子邮箱',
      password: '密码',
      passwordConfirmation: '确认密码',
      submit: '创建账户',
      existingAccount: '已有账户？',
    },
    password: {
      forgotTitle: '重置密码',
      forgotDescription: '输入你的电子邮箱，我们会发送密码重置链接。',
      sendResetLink: '发送重置链接',
      resetTitle: '设置新密码',
      resetSubmit: '重置密码',
      confirmTitle: '确认密码',
    },
    verification: {
      title: '验证电子邮箱',
      resend: '重新发送验证邮件',
    },
    twoFactor: {
      title: '双重身份验证',
      code: '验证码',
      recoveryCode: '恢复代码',
      submit: '继续',
    },
    invitation: {
      title: '联盟邀请',
      accept: '接受邀请',
    },
  },
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
} satisfies MessageCatalogue;

export default messages;
