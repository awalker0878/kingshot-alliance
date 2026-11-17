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
      forgotTitle: '계정 복구',
      forgotDescription: 'Kingshot Alliance 계정에 연결된 이메일 주소를 입력하세요.',
      sendResetLink: '복구 안내 보내기',
      resetTitle: '새 비밀번호 선택',
      resetSubmit: '비밀번호 재설정',
      confirmTitle: '본인 확인',
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
      intro: '총독 모집, 이벤트 준비, 연맹 관리, 왕국 현황 확인을 한곳에서 진행하세요.',
    },
    login: {
      intro: '계정으로 로그인한 다음 사용할 총독을 선택하세요.',
      invitationNotice: '연맹 초대를 계속 수락하려면 초대받은 계정으로 로그인하세요.',
      needAccount: '계정이 필요하신가요?',
      register: '가입',
    },
    register: {
      intro:
        '하나의 계정은 여러 총독을 보유할 수 있습니다. 연맹 내 직급과 왕국 내 임무는 항상 선택한 총독에 따라 결정됩니다.',
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
      resetIntro: '아래 계정에 사용할 새 Kingshot Alliance 비밀번호를 설정하세요.',
      newPassword: '새 비밀번호',
      confirmNewPassword: '새 비밀번호 확인',
      confirmDescription:
        '이 작업은 보호된 연맹 접근 권한을 변경합니다. 계속하기 전에 본인을 확인하세요.',
      recoveryKicker: '계정 복구',
      recoveryNeutral:
        '해당 이메일 주소로 비밀번호를 복구할 수 있는 경우, 안전하게 재설정하는 방법을 보내드립니다.',
      checkInboxKicker: '받은 편지함 확인',
      checkInboxHeading: '복구 안내를 요청했습니다',
      checkInboxDescription:
        '입력한 주소로 비밀번호를 복구할 수 있는 경우, 재설정 안내를 보냈습니다. 보안상의 이유로 계정의 존재 여부나 사용 중인 로그인 방법은 확인해 드릴 수 없습니다.',
      resetKicker: '안전한 비밀번호 재설정',
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
      description: 'Kingshot Alliance 계정의 보안 확인 링크를 다음 이메일 주소로 보냈습니다:',
      sent: '새 인증 링크를 보냈습니다.',
      kicker: '이메일 주소 확인',
      instructions: '메시지를 열고 링크를 따라 이메일 주소 확인을 완료합니다.',
      securityNote: 'Kingshot Alliance는 이메일 주소 확인 메시지에서 비밀번호를 요청하지 않습니다.',
      resendIn: '{seconds}초 후에 다시 보낼 수 있습니다',
      useAnotherAccount: '로그아웃하고 다른 계정 사용',
    },
    twoFactor: {
      kicker: '계정 보안',
      description: '인증 앱의 현재 6자리 코드를 입력하세요.',
      verifyCode: '코드 확인',
      useRecoveryCode: '복구 코드 사용',
    },
    social: {
      continueWithGoogle: 'Google로 계속하기',
      createWithGoogle: 'Google로 Kingshot Alliance 계정 만들기',
      or: '또는',
    },
    passkeys: {
      signIn: '패스키로 로그인',
      authenticating: '패스키 확인 중…',
    },
    confirm: {
      title: '본인 확인',
      description:
        '이 중요한 계정 변경을 계속하려면 계정에 연결된 로그인 방법 중 하나로 본인을 확인하세요.',
      usePassword: '비밀번호로 확인',
      useGoogle: 'Google로 확인',
      usePasskey: '패스키로 확인',
    },
  },
} satisfies MessageCatalogue;

export default messages;
