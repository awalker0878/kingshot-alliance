import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: '로그인',
      email: '이메일',
      password: '비밀번호',
      remember: '로그인 상태 유지',
      forgotPassword: '비밀번호를 잊으셨나요?',
      submit: '로그인',
      createAccount: '계정 만들기',
      invitation: '초대장이 있나요?',
    },
    register: {
      title: '계정 만들기',
      name: '이름',
      email: '이메일',
      password: '비밀번호',
      passwordConfirmation: '비밀번호 확인',
      submit: '계정 만들기',
      existingAccount: '이미 계정이 있나요?',
    },
    password: {
      forgotTitle: '비밀번호 재설정',
      forgotDescription: '이메일 주소를 입력하면 비밀번호 재설정 링크를 보내드립니다.',
      sendResetLink: '재설정 링크 보내기',
      resetTitle: '새 비밀번호 선택',
      resetSubmit: '비밀번호 재설정',
      confirmTitle: '비밀번호 확인',
    },
    verification: {
      title: '이메일 확인',
      resend: '확인 이메일 다시 보내기',
    },
    twoFactor: {
      title: '2단계 인증',
      code: '인증 코드',
      recoveryCode: '복구 코드',
      submit: '계속',
    },
    invitation: {
      title: '연맹 초대',
      accept: '초대 수락',
    },
  },
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
      invitationNotice:
        '{email} 계정으로 {alliance}에 초대되었습니다. 계정을 만들면 이 초대도 수락됩니다.',
      invitationOnly: '현재 가입은 초대 전용입니다. 연맹에서 보낸 초대 링크를 여세요.',
      timezone: '시간대',
      passwordHint: '대문자, 소문자, 숫자를 포함해 12자 이상.',
      existingAccount: '이미 계정이 있으신가요?',
    },
    invitation: {
      join: '{alliance} 가입',
      forEmail: '이 초대는 {email}용입니다.',
      expires: '만료: {date}',
      wrongAccount:
        '{email}로 로그인되어 있습니다. 이 초대를 수락하려면 초대받은 이메일로 로그인하세요.',
      createAndJoin: '계정 만들고 가입',
      signInAccept: '로그인하여 수락',
    },
    password: {
      backToSignIn: '로그인으로 돌아가기',
      resetIntro: '비밀번호를 재설정하면 개인 액세스 토큰이 취소됩니다.',
      newPassword: '새 비밀번호',
      confirmNewPassword: '새 비밀번호 확인',
      confirmDescription:
        '이 작업은 연맹 접근 또는 권한을 변경하므로 비밀번호를 다시 확인해야 합니다.',
    },
    verification: {
      description:
        '{email}로 인증 링크를 보냈습니다. 보호된 계정 작업 전에 이메일 주소를 인증하세요.',
      sent: '새 인증 링크를 보냈습니다.',
    },
    twoFactor: {
      kicker: '보안 확인',
      description: '인증 앱의 현재 6자리 코드를 입력하세요.',
      verifyCode: '코드 확인',
      useRecoveryCode: '복구 코드 사용',
    },
  },
} satisfies MessageCatalogue;

export default messages;
