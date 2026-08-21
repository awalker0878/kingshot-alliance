import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: '직위 특전 알림',
    },
  },
  accountExperience: {
    account: {
      eyebrow: '계정 관리',
      title: '계정 및 보안',
      intro: '신원, 이메일 인증, 비밀번호, 2단계 인증 및 활성 세션을 관리합니다.',
      passwordUpdated: '비밀번호가 업데이트되었고 다른 인증 세션에서 로그아웃했습니다.',
      sessionsRevoked: '다른 인증 세션에서 로그아웃했습니다.',
      twoFactorDisabled: '2단계 인증이 비활성화되었습니다.',
      profileTitle: '프로필',
      profileIntro: '이메일 주소를 변경하면 다시 인증해야 합니다.',
      timezone: '시간대',
      saveProfile: '프로필 저장',
      emailVerification: '이메일 인증',
      verified: '인증됨',
      pending: '대기 중',
      twoFactorState: '2단계 인증',
      enabled: '활성화',
      setupPending: '설정 대기 중',
      notEnabled: '비활성화',
      twoFactorTitle: '2단계 인증',
      twoFactorIntro:
        '인증 앱으로 로그인을 보호합니다. 복구 코드는 생성 또는 재생성할 때만 표시됩니다.',
      startSetup: '설정 시작',
      authenticatorSecret: '인증 앱 비밀키',
      provisioningUri: '프로비저닝 URI',
      authenticationCode: '인증 코드',
      confirm: '확인',
      saveRecoveryCodes: '복구 코드를 지금 저장하세요',
      recoveryIntro: '각 코드는 한 번만 사용할 수 있습니다. 이 계정과 분리된 곳에 보관하세요.',
      regenerateRecoveryCodes: '복구 코드 재생성',
      disableTwoFactor: '2단계 인증 비활성화',
      passwordTitle: '비밀번호 변경',
      passwordIntro: '비밀번호를 변경하면 다른 기기에서 로그아웃되고 다른 활성 접근도 종료됩니다.',
      currentPassword: '현재 비밀번호',
      newPassword: '새 비밀번호',
      confirmNewPassword: '새 비밀번호 확인',
      updatePassword: '비밀번호 업데이트',
      sessionsTitle: '다른 세션',
      sessionsIntro: '이 기기를 제외한 모든 인증 세션에서 로그아웃합니다.',
      signOutOthers: '다른 기기에서 로그아웃',
      dangerTitle: '위험 구역',
      deleteAccount: '계정 삭제',
    },
    deletion: {
      eyebrow: '계정 수명 주기',
      title: '계정 삭제',
      intro:
        '삭제에는 7일의 유예 기간이 있습니다. 활성 동맹 소유권, 플랫폼 관리자 접근, 법적 보존 조치가 처리를 막을 수 있습니다. 처리된 계정은 감사 기록을 삭제하지 않고 익명화됩니다.',
      currentRequest: '현재 요청',
      status: '상태',
      eligibleAt: '처리 가능 시각',
      requestedAt: '요청 시각',
      processedAt: '처리 시각',
      notYet: '아직',
      requestTitle: '삭제 요청',
      requestIntro:
        '소유한 동맹의 소유권을 먼저 이전하세요. 법적 보존 또는 보안·감사에 필요한 기록은 가명 처리 형태로 유지됩니다.',
      requestButton: '계정 삭제 요청',
      confirm:
        '계정 삭제를 요청하시겠습니까? 7일의 유예 기간과 소유권/법적 보존 확인이 적용됩니다.',
      requested: '계정 삭제 요청이 기록되었습니다.',
      backToAccount: '계정 및 보안으로 돌아가기',
    },
  },
} satisfies MessageCatalogue;

export default messages;
