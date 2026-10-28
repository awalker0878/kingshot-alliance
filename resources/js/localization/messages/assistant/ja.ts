import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'アシスタント',
    title: '同盟に質問',
    eyebrow: '同盟アシスタント · 権限に基づく回答',
    subtitle:
      'イベント、自分のロスターとRSVP、自分の割り当て、同盟ガイドと観測、公開済み領土プラン、出典付きゲームデータ、対象範囲内の移転準備状況について質問できます。',
    authorizationHint: '回答には、あなたが閲覧を許可されているデータだけを使用します。',
    tryAsking: '質問してみる',
    conversation: '同盟アシスタントとの会話',
    youAsked: 'あなたの質問',
    possibleEvents: '候補のイベント',
    openEvent: 'イベントを開く',
    sourcesHeading: '使用した情報源',
    sourceTime: '情報源の時刻: {time}',
    questionLabel: '同盟に質問',
    questionPlaceholder: 'Swordland は何時で、私はロスターに入っていますか？',
    inputHint: '{count}/{max} 文字 · Enter で質問 · Shift+Enter で改行',
    asking: '情報源を確認中…',
    ask: '質問',
    notRecorded: '記録なし',
    classifications: {
      operational_fact: '運用上の事実',
      game_fact: 'ゲームデータ',
      alliance_strategy: '同盟戦略',
      observation: '観察',
      alliance_command: '士官の概要',
      event_readiness: '事件の準備',
      bear_hunt_run: 'ベア・ハンティング・ラン',
      roster_freshness: '知事の観察の新鮮さ',
      transfer_verification: '転送検証',
      territory_comparison: '領域比較',
    },
    sources: {
      event: 'イベント',
      roster: 'ロスター',
      alliance_content: '同盟ガイド',
      observation: '観察',
      game_fact: 'ゲームデータ',
      participation: '参加状況',
      battle_plan_assignment: '戦闘計画の割り当て',
      transfer_assessment: '移転評価',
      territory_plan_revision: '公開済み領土プラン',
      alliance_command: '士官の概要',
      event_readiness: '事件の準備',
      bear_hunt_run: 'ベア・ハンティング・ラン',
      roster_freshness: '知事の観察の新鮮さ',
      transfer_verification: '転送検証',
      territory_comparison: '領域比較',
      alliance_settings: '連合の設定',
      alliance_governance_history: '連合の歴史を',
      alliance_roster_reconciliation: '協力者のリクエスト調整',
    },
    prompts: {
      swordland: 'Swordland は何時で、私はロスターに入っていますか？',
      nextEvent: '次のイベントは何ですか？',
      bearGuide: 'Bear Hunt ガイドには何と書いてありますか？',
      observation: '相手について何を観察していますか？',
      heroFact: 'Amadeus は第何世代？',
      rsvpWeek: '今週RSVPしたイベントは？',
      battleAssignment: 'Swordlandでの自分の割り当ては？',
      transferStatus: '移転に足りないものは？',
      territoryPlan: 'Bear Huntではどのハイブ配置を使う？',
      allianceCommand: '警官は何に注意を払うべきですか？',
      eventReadiness: '次の確認イベントは準備はいいか？',
      rallyGaps: '次のイベントのためにどのくらいのラリーギャップが残っていますか？',
      bearHuntHistory: 'クマ狩りの歴史を見せて',
      progressionFreshness: 'どの知事の観測が 時代遅れか 欠落している？',
      transferVerification: 'どの転送参加者が検証を必要としますか？',
      intelligenceChanges: '物質的な知性は最近どう変わった？',
      territoryComparison: '観測地域は，公表された計画とどのように比較されるか。',
    },
    answers: {
      transferVerificationIncomplete:
        'この概要では移転参加者 {total} 人のうち {assessed} 人を評価しました。少なくとも {count} 人に確認が必要で、{unassessed} 人はここでは未評価です。最新の確認には移転準備を開いてください。',
      help: '許可されたイベント、自分のロスターとRSVP、自分の割り当て、同盟ガイドと観測、公開済み領土プラン、出典付きゲームデータ、対象範囲内の移転状況から回答できます。出典のないKingShot知識は使いません。',
      unsupported:
        '対応する許可済みソースからのみ回答できます。一般的なKingShot知識は使わず、Assistantから変更もしません。',
      unavailable: '現在、同盟アシスタントは情報源を確認できません。もう一度お試しください。',
      rateLimited: '質問の頻度が高すぎます。少し待ってからお試しください。',
      validationError: '2〜{max} 文字で質問を入力してください。',
      noUpcomingEvent: 'あなたが閲覧できる今後のイベントは見つかりませんでした。',
      eventSubjectMissing: '確認したいイベント名を指定してください。',
      eventNotFound: '「{subject}」に一致する、閲覧可能な今後のイベントは見つかりませんでした。',
      eventAmbiguous:
        '「{subject}」に一致するイベントが複数あります。下から対象のイベントを開いてください。',
      eventTime: '{event} は {startsAt} に開始します。',
      eventTimeNotRostered:
        '{event} は {startsAt} に開始します。現在あなたはロスターに入っていません。',
      notRostered: '現在、{event} のロスターには入っていません。',
      eventTimeRostered:
        '{event} は {startsAt} に開始します。あなたは {roster} に入っています。役割: {role}、スロット: {slot}、状態: {status}。',
      rostered:
        '{event} では {roster} に入っています。役割: {role}、スロット: {slot}、状態: {status}。',
      contentSubjectMissing: '確認したい同盟ガイドのイベントまたはトピックを指定してください。',
      contentNotFound: '「{subject}」に一致する公開済み同盟コンテンツは見つかりませんでした。',
      contentFound: '同盟戦略 — {title}: {excerpt}',
      observationSubjectMissing: '確認したい同盟または観察対象を指定してください。',
      observationNotFound: '「{subject}」に一致する、閲覧可能な観察情報は見つかりませんでした。',
      observationFound: '観察 — {title}: {observation}',
      gameFactKnown:
        'ゲームデータ — {title}。下の出典付き値はデータセット {datasetVersion} から取得しています。',
      gameFactUnknown:
        'ゲームデータ — {title} について対応する既知の値がありません。推測はしません。',
      gameFactConflicting:
        'ゲームデータ — {title} の維持された証拠が競合しています。こちらで値を選びません。',
      participationNone: '{event} にあなたのRSVPまたは登録記録はありません。',
      participationFound: '{event} の参加状況を下に表示します。',
      participationWeekNone: '今週のあなたのRSVPまたは登録記録は見つかりませんでした。',
      participationNotFound: '一致するRSVP、登録、待機リスト状態は見つかりませんでした。',
      participationWeek: '今週の参加記録が {count} 件見つかりました。',
      participationList: '一致する参加記録が {count} 件見つかりました。',
      battlePlanNotFound:
        'あなたへの割り当てがある、閲覧可能な今後のイベントは見つかりませんでした。',
      battlePlanNone: '現在、{event} の戦闘計画割り当てはありません。',
      battlePlanOne: '{event} の戦闘計画割り当てが1件あります。',
      battlePlanMany: '{event} の戦闘計画割り当てが {count} 件あります。',
      transferNotInScope: '現在の許可された移転範囲から移転準備状況を提供できません。',
      transferStatus: '移転評価は {outcome} です。所有機能が計算した要件を下に表示します。',
      territoryPlanNotFound: '{event} に公開済み領土プランのリビジョンは添付されていません。',
      territoryPlanAmbiguous:
        '{event} に複数の公開済み領土プランが添付されています。勝手には選びません。',
      territoryPlanFound:
        '{event} では {planName} のリビジョン {revisionNumber} を {purpose} 用に使用しています。',
      rosterWriteHandoff:
        'ここからロスターは変更できません。通常の {event} ワークフローを開いて変更してください。',
      ownerEventWriteHandoff:
        'アシスタントからこの変更はできません。 続けるには通常の {event} 所有者ワークフローを開きます。',
      ownerWriteHandoff:
        'アシスタントから {owner} の状態を変更できません。 現在の認可事実は以下のとおりです。続けるにはオーナワークフローを開きます。',
      allianceCommandAttention:
        '幹部向けの概要には、対応が必要な事実項目が{count}件あります。閲覧権限のあるソースを以下に表示します。',
      allianceCommandAttentionNotAvailable: 'この同盟に関する公認された士官概要事実は存在しない。',
      eventReadiness:
        '次の確認済みイベントには、準備を妨げる項目が{count}件あります。担当者の状況表示は以下のとおりです。',
      eventReadinessNotAvailable:
        'あなたの権限者の概要で 確認されたイベント準備状態が 見つからなかった',
      rallyGaps:
        '次の確認済みイベントには、集結の準備不足が{count}件あります。集結に関する事実情報を以下に表示します。',
      rallyGapsNotAvailable:
        '検証されたイベントにマッチするラリー準備状態の事実を見つけることができませんでした。',
      bearHuntHistory:
        '{count}の最近の認可されたベアハンティングの 走行記録を見つけた 記録された状態と受け入れられた証拠状態は以下のように明示的である。',
      bearHuntHistoryNotAvailable:
        '歴史を支持する 認可された 完了した熊狩りの走行を見つけられませんでした',
      progressionFreshness:
        '{count}のガバナンス観測は 既に過去のものか または 認可されたロスターの新鮮度予測に欠けている',
      progressionFreshnessNotAvailable: '知事の観察の新鮮度は あなたの権限者の概要で利用できません',
      transferVerification:
        '{count} 転送参加者は確認が必要か，またはオーナアセスメントでブロックされています。',
      transferVerificationNotAvailable: '現時点で公認された移行検証計画はない。',
      territoryComparison:
        '権限のある領地比較には、観測された差分が{count}件あります。不足している観測や互換性のない観測は、以下に明示されています。',
      territoryComparisonNotAvailable: '権威付けされた公表された領土比較は利用可能ではない。',
      allianceSettings:
        '現在の同盟の設定は {name}、URL名 {slug}、言語 {language}、タイムゾーン {timezone} です。',
      allianceGovernanceHistory:
        '閲覧権限のある最近の同盟運営イベントが{count}件見つかりました。これらは担当者の監査記録に基づく読み取り専用の事実情報です。',
      allianceRosterReconciliation:
        '最新の承認済みメンバー観測には、確認が必要な項目が{needsReview}件、現在のメンバーと一致する項目が{matched}件あります。',
      allianceRosterReconciliationNotAvailable:
        'まだ調整できる受け入れられた同盟のロスター観測はありません。',
    },
    possibleMatches: '候補',
    detailsHeading: '回答の詳細',
    requirementsHeading: '移転要件',
    datasetVersion: 'データセット版',
    evidenceStatus: '証拠ステータス',
    checksum: 'チェックサム',
    revisionLabel: 'リビジョン {revision}',
    participationSummary: 'RSVP: {response} · 登録: {registration}',
    waitlistPosition: '待機リスト順位: {position}',
    teamLabel: 'チーム: {team}',
    handoffs: {
      openRoster: 'イベントロスターを開く',
      openOwnerWorkflow: '開いたオーナーワークフロー',
    },
    transferRequirements: {
      window_phase: '移転期間フェーズ',
      transfer_group: '移転グループ',
      power_cap: '戦力上限',
      invitation: '招待',
      transfer_passes: '移転パス',
      in_game_rules: 'ゲーム内ルール',
    },
    transferStates: {
      met: '達成',
      unmet: '未達成',
      unknown: '不明',
      stale: '古い',
      conflicting: '競合',
      not_applicable: '対象外',
    },
    requirementValues: '現在: {actual} · 必要: {required}',
  },
  intelligenceChange: {
    title: '最近のインテリジェンス変更',
    subtitle:
      '認可された履歴から導出した出典付きの変更です。観測事実を示し、戦略的意図は推測しません。',
    empty: 'この範囲に重要なインテリジェンス変更はありません。',
    source: '出典',
    observed: '観測',
    baseline: '基準',
    current: '現在',
    previous: '以前',
    viewSource: '出典を見る',
    material: '重要な変更',
    attention: '確認が必要',
  },
} satisfies MessageCatalogue;

export default messages;
