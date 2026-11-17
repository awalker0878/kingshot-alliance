import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: '도우미',
    title: '내 동맹에 질문하기',
    eyebrow: '동맹 도우미 · 권한 기반 답변',
    subtitle:
      '이벤트, 내 로스터와 RSVP, 내 배정, 얼라이언스 가이드와 관측, 게시된 영토 계획, 출처가 있는 게임 데이터, 범위 내 이전 준비 상태를 질문할 수 있습니다.',
    authorizationHint: '답변에는 내가 볼 권한이 있는 데이터만 사용됩니다.',
    tryAsking: '이렇게 물어보세요',
    conversation: '동맹 도우미 대화',
    youAsked: '내 질문',
    possibleEvents: '가능한 이벤트',
    openEvent: '이벤트 열기',
    sourcesHeading: '사용한 출처',
    sourceTime: '출처 시간: {time}',
    questionLabel: '내 동맹에 질문하기',
    questionPlaceholder: 'Swordland는 몇 시이고 제가 로스터에 있나요?',
    inputHint: '{count}/{max}자 · Enter로 질문 · Shift+Enter로 줄바꿈',
    asking: '출처 확인 중…',
    ask: '질문',
    notRecorded: '기록 없음',
    classifications: {
      operational_fact: '운영 사실',
      game_fact: '게임 데이터',
      alliance_strategy: '동맹 전략',
      observation: '관찰',
      alliance_command: '직원 개요',
      event_readiness: '준비됨',
      bear_hunt_run: '곰 사냥 실행',
      roster_freshness: '주지사 관찰 신선도',
      transfer_verification: '전송 확인',
      territory_comparison: '영토 비교',
    },
    sources: {
      event: '이벤트',
      roster: '로스터',
      alliance_content: '동맹 가이드',
      observation: '관찰',
      game_fact: '게임 데이터',
      participation: '참여',
      battle_plan_assignment: '전투 계획 배정',
      transfer_assessment: '이전 평가',
      territory_plan_revision: '게시된 영토 계획',
      alliance_command: '직원 개요',
      event_readiness: '준비됨',
      bear_hunt_run: '곰 사냥 실행',
      roster_freshness: '주지사 관찰 신선도',
      transfer_verification: '전송 확인',
      territory_comparison: '영토 비교',
      alliance_settings: '동맹 설정',
      alliance_governance_history: '동맹 거버넌스 역사',
      alliance_roster_reconciliation: '동맹 명단 조정',
    },
    prompts: {
      swordland: 'Swordland는 몇 시이고 제가 로스터에 있나요?',
      nextEvent: '제 다음 이벤트는 무엇인가요?',
      bearGuide: '우리 Bear Hunt 가이드에는 뭐라고 되어 있나요?',
      observation: '상대에 대해 무엇을 관찰했나요?',
      heroFact: 'Amadeus는 몇 세대인가요?',
      rsvpWeek: '이번 주에 어떤 이벤트에 RSVP했나요?',
      battleAssignment: '내 Swordland 배정은 무엇인가요?',
      transferStatus: '이전에 무엇이 부족한가요?',
      territoryPlan: 'Bear Hunt에 어떤 하이브 배치를 사용하나요?',
      allianceCommand: '경관님, 뭘 봐야 하나요?',
      eventReadiness: '다음 확인된 이벤트가 준비되었습니까?',
      rallyGaps: '다음 이벤트에 남아있는 랠리 간격은 무엇입니까?',
      bearHuntHistory: '우리의 최근 베어 헌트 성과 역사를 보여줍니다.',
      progressionFreshness: '어떤 주지사 관측이 낡았거나 누락되었습니까?',
      transferVerification: '어떤 트랜스퍼 참가자가 인증이 필요합니까?',
      intelligenceChanges: '최근에 어떤 물질 지능이 변경되었습니까?',
      territoryComparison: '관찰된 영토는 발표된 계획과 어떻게 비교됩니까?',
    },
    answers: {
      transferVerificationIncomplete:
        '이 개요는 이전 참가자 {total}명 중 {assessed}명을 평가했습니다. 최소 {count}명에게 확인이 필요하며 {unassessed}명은 여기서 평가되지 않았습니다. 현재 상태를 확인하려면 이전 준비를 여세요.',
      help: '권한이 있는 이벤트, 내 로스터와 RSVP, 내 배정, 얼라이언스 가이드와 관측, 게시된 영토 계획, 출처가 있는 게임 데이터, 범위 내 이전 상태에서 답할 수 있습니다. 출처 없는 KingShot 지식은 사용하지 않습니다.',
      unsupported:
        '지원되는 권한 있는 출처에서만 답할 수 있습니다. 일반 KingShot 지식은 사용하지 않으며 Assistant에서 변경하지 않습니다.',
      unavailable: '현재 동맹 도우미가 출처를 확인할 수 없습니다. 다시 시도하세요.',
      rateLimited: '질문이 너무 빠릅니다. 잠시 후 다시 시도하세요.',
      validationError: '2자에서 {max}자 사이의 질문을 입력하세요.',
      noUpcomingEvent: '볼 권한이 있는 예정 이벤트를 찾지 못했습니다.',
      eventSubjectMissing: '확인할 이벤트 이름을 알려주세요.',
      eventNotFound: '“{subject}”와 일치하는 권한 있는 예정 이벤트를 찾지 못했습니다.',
      eventAmbiguous:
        '“{subject}”와 일치하는 이벤트가 여러 개 있습니다. 아래에서 원하는 이벤트를 여세요.',
      eventTime: '{event}은(는) {startsAt}에 시작합니다.',
      eventTimeNotRostered: '{event}은(는) {startsAt}에 시작합니다. 현재 로스터에 없습니다.',
      notRostered: '현재 {event} 로스터에 없습니다.',
      eventTimeRostered:
        '{event}은(는) {startsAt}에 시작합니다. {roster}에 배정되어 있습니다. 역할: {role}; 슬롯: {slot}; 상태: {status}.',
      rostered:
        '{event}에서 {roster}에 배정되어 있습니다. 역할: {role}; 슬롯: {slot}; 상태: {status}.',
      contentSubjectMissing: '확인할 동맹 가이드의 이벤트나 주제를 알려주세요.',
      contentNotFound: '“{subject}”와 일치하는 게시된 동맹 콘텐츠를 찾지 못했습니다.',
      contentFound: '동맹 전략 — {title}: {excerpt}',
      observationSubjectMissing: '확인할 동맹이나 관찰 주제를 알려주세요.',
      observationNotFound: '“{subject}”와 일치하는 권한 있는 관찰 정보를 찾지 못했습니다.',
      observationFound: '관찰 — {title}: {observation}',
      gameFactKnown:
        '게임 데이터 — {title}. 아래 출처 기반 값은 데이터셋 {datasetVersion}에서 가져왔습니다.',
      gameFactUnknown:
        '게임 데이터 — {title}에 대해 지원되는 알려진 값이 없습니다. 추측하지 않습니다.',
      gameFactConflicting:
        '게임 데이터 — {title}에 대한 유지된 증거가 서로 충돌합니다. 임의로 값을 선택하지 않습니다.',
      participationNone: '{event}에 기록된 RSVP 또는 등록이 없습니다.',
      participationFound: '{event} 참여 상태가 아래에 표시됩니다.',
      participationWeekNone: '이번 주에 기록된 RSVP 또는 등록이 없습니다.',
      participationNotFound: '일치하는 RSVP, 등록 또는 대기 목록 상태를 찾지 못했습니다.',
      participationWeek: '이번 주 참여 기록 {count}건을 찾았습니다.',
      participationList: '일치하는 참여 기록 {count}건을 찾았습니다.',
      battlePlanNotFound: '내 배정이 있는 권한 있는 예정 이벤트를 찾지 못했습니다.',
      battlePlanNone: '현재 {event} 전투 계획 배정이 없습니다.',
      battlePlanOne: '{event} 전투 계획 배정이 1건 있습니다.',
      battlePlanMany: '{event} 전투 계획 배정이 {count}건 있습니다.',
      transferNotInScope: '현재 권한 있는 이전 범위에서는 이전 준비 상태를 제공할 수 없습니다.',
      transferStatus:
        '이전 평가는 {outcome}입니다. 소유 기능이 계산한 요구 사항을 아래에 표시합니다.',
      territoryPlanNotFound: '{event}에 게시된 영토 계획 리비전이 연결되어 있지 않습니다.',
      territoryPlanAmbiguous:
        '{event}에 여러 게시 영토 계획 리비전이 연결되어 있어 임의로 선택하지 않습니다.',
      territoryPlanFound:
        '{event}에서는 {purpose} 용도로 {planName} 리비전 {revisionNumber}을 사용합니다.',
      rosterWriteHandoff:
        '여기서는 로스터를 변경할 수 없습니다. 일반 {event} 워크플로를 열어 변경하세요.',
      ownerEventWriteHandoff:
        '마법사에서 이 변경을 수행할 수 없습니다. 계속하려면 일반 {event} 소유자 워크플로를 엽니다.',
      ownerWriteHandoff:
        '마법사에서 {owner} 상태를 변경할 수 없습니다. 현재 승인된 사실은 아래에 표시되어 있습니다. 계속하려면 소유자 워크플로를 엽니다.',
      allianceCommandAttention:
        '직원 개요에는 주의를 요하는 {count}개의 사실상 항목이 있습니다. 승인된 출처는 아래에 나타납니다.',
      allianceCommandAttentionNotAvailable: '이 동맹에 대한 승인된 장교 개요 사실이 없습니다.',
      eventReadiness:
        '다음 확인된 이벤트는 {count} 차단 준비 항목을 가지고 있습니다. 본관은 아래와 같다.',
      eventReadinessNotAvailable:
        '승인된 담당자 개요에서 일치하는 확인된 이벤트 준비 상태를 찾을 수 없습니다.',
      rallyGaps:
        '다음 확인된 이벤트는 {count} 집회 준비 간격이 있습니다. 라이브 버전의 곡은 아래와 같다.',
      rallyGapsNotAvailable: '일치하는 확인된 이벤트에 대한 랠리 준비 사실을 찾을 수 없습니다.',
      bearHuntHistory:
        '나는 {count} 최근 승인 베어 사냥 실행 기록을 발견 (s). 증거와 증명의 형식은 아래와 같다.',
      bearHuntHistoryNotAvailable:
        '허가된 완료된 베어 헌트 실행을 지원하는 기록을 찾을 수 없습니다.',
      progressionFreshness:
        '{count} 총재 관찰(s)은 허가된 로스터 신선도 예측에서 낡았거나 누락되었습니다.',
      progressionFreshnessNotAvailable:
        '총독 관찰 신선도는 승인된 장교 개요에서 사용할 수 없습니다.',
      transferVerification:
        '{count} 전송 참가자가 인증이 필요하거나 소유자 평가에서 차단되었습니다.',
      transferVerificationNotAvailable: '현재 승인된 전송 검증 예측은 없습니다.',
      territoryComparison:
        '승인된 영토 비교는 {count}개의 관찰된 차이점을 포함합니다. 누락된 점과 호환되지 않는 점은 아래에 명시되어 있다.',
      territoryComparisonNotAvailable: '허가된 출판 영토 비교가 사용 가능하지 않습니다.',
      allianceSettings:
        '현재 동맹 설정은 {name}, URL 이름 {slug}, 언어 {language}, 시간대 {timezone}입니다.',
      allianceGovernanceHistory:
        '나는 {count} 최근 승인된 동맹 관리 이벤트를 찾았다. 이것은 읽기 전용 소유자 감사 사실입니다.',
      allianceRosterReconciliation:
        '최근 승인된 로스터 관찰에는 {needsReview}개의 검토가 필요한 항목과 {matched}개의 현재 회원과 일치하는 항목이 있습니다.',
      allianceRosterReconciliationNotAvailable:
        '아직 합의된 얼라이언스 로스터 관측을 조정할 수 없습니다.',
    },
    possibleMatches: '가능한 일치 항목',
    detailsHeading: '답변 세부 정보',
    requirementsHeading: '이전 요구 사항',
    datasetVersion: '데이터셋 버전',
    evidenceStatus: '증거 상태',
    checksum: '체크섬',
    revisionLabel: '리비전 {revision}',
    participationSummary: 'RSVP: {response} · 등록: {registration}',
    waitlistPosition: '대기 목록 순위: {position}',
    teamLabel: '팀: {team}',
    handoffs: {
      openRoster: '이벤트 로스터 열기',
      openOwnerWorkflow: '소유자 워크플로우 열기',
    },
    transferRequirements: {
      window_phase: '이전 기간 단계',
      transfer_group: '이전 그룹',
      power_cap: '전투력 상한',
      invitation: '초대',
      transfer_passes: '이전 패스',
      in_game_rules: '게임 내 규칙',
    },
    transferStates: {
      met: '충족',
      unmet: '미충족',
      unknown: '알 수 없음',
      stale: '오래됨',
      conflicting: '충돌',
      not_applicable: '해당 없음',
    },
    requirementValues: '현재: {actual} · 필요: {required}',
  },
  intelligenceChange: {
    title: '최근 정보 변경',
    subtitle:
      '권한이 있는 기록에서 도출된 출처 인용 변경입니다. 관측을 설명하며 전략적 의도를 추론하지 않습니다.',
    empty: '이 범위에 중요한 정보 변경이 없습니다.',
    source: '출처',
    observed: '관측',
    baseline: '기준',
    current: '현재',
    previous: '이전',
    viewSource: '출처 보기',
    material: '중요 변경',
    attention: '확인 필요',
  },
} satisfies MessageCatalogue;

export default messages;
