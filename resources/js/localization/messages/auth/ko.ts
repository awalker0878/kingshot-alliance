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
      recoveryKicker: '계정 복구',
      recoveryNeutral:
        '해당 이메일에 해당하는 비밀번호 자격 증명이 존재하는 경우, 보안 비밀번호 재설정 지침을 보내드립니다.',
      checkInboxKicker: '받은 편지함 확인',
      checkInboxHeading: '복구 지침 요청됨',
      checkInboxDescription:
        '입력한 주소에 해당하는 암호 자격 증명이 존재하는 경우 암호 재설정 지침이 전송되었습니다. 보안상의 이유로, 계정이 존재하는지 또는 어떤 방법을 사용하는지 확인할 수 없습니다.',
      resetKicker: '보안 암호 재설정',
      accountEmail: '계정 이메일',
      requirements: '비밀번호에는 다음 사항이 포함되어야 합니다.',
      requirementLength: '최소 12자',
      requirementUpper: '대문자',
      requirementLower: '소문자',
      requirementNumber: '숫자',
      showPassword: '비밀번호 보이기',
      hidePassword: '비밀번호 숨기기',
      validationSummary: '계속하기 전에 강조 표시된 필드를 검토하십시오.',
    },
    verification: {
      description:
        '아래 이메일 주소로 인증 링크를 보냈습니다. 보호된 계정 작업 전에 이메일 주소를 인증하세요.',
      sent: '새 인증 링크를 보냈습니다.',
      kicker: '이메일 확인',
      instructions: '메시지를 열고 링크를 따라 이메일 주소 확인을 완료합니다.',
      securityNote:
        'Kingshot Alliance은 이메일 확인 메시지를 통해 절대로 비밀번호를 요청하지 않습니다.',
      resendIn: '{seconds}초 안에 다시 보낼 수 있습니다',
      useAnotherAccount: '로그아웃하고 다른 계정 사용',
    },
    twoFactor: {
      kicker: '보안 확인',
      description: '인증 앱의 현재 6자리 코드를 입력하세요.',
      verifyCode: '코드 확인',
      useRecoveryCode: '복구 코드 사용',
    },
    social: {
      continueWithGoogle: 'Google로 계속',
      createWithGoogle: 'Google와 함께 Kingshot Alliance 계정을 만듭니다.',
      or: '또는',
    },
    passkeys: {
      signIn: '패스키로 로그인',
      authenticating: '패스키 확인 중…',
    },
    confirm: {
      title: '비밀번호 확인',
      description: '이 민감한 계정 변경을 계속하기 전에 첨부된 로그인 방법 중 하나를 사용하십시오.',
      usePassword: '비밀번호로 확인',
      useGoogle: 'Google로 확인',
      usePasskey: '패스키로 확인',
    },
  },
} satisfies MessageCatalogue;

export default messages;
