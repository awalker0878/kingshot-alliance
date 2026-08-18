import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: '言語',
    signIn: 'ログイン',
    signOut: 'ログアウト',
    createAccount: 'アカウント作成',
    continue: '続ける',
    cancel: 'キャンセル',
    save: '保存',
    close: '閉じる',
    loading: '読み込み中',
    menu: 'メニュー',
    openNavigation: 'ナビゲーションを開く',
    closeNavigation: 'ナビゲーションを閉じる',
    playerAlliance: 'アクティブプレイヤーの同盟',
    noPlayerAlliance: 'アクティブプレイヤーには有効な同盟メンバーシップがありません。',
    skipToContent: 'コンテンツへ移動',
  },
  navigation: {
    home: 'ホーム',
    dashboard: 'ダッシュボード',
    alliance: '同盟',
    events: 'イベント',
    roster: 'メンバー',
    recruitment: '募集',
    content: 'コンテンツ',
    contributions: '貢献',
    kingdom: '王国',
    transfers: '移民',
    integrations: '連携',
    profile: 'プロフィール',
    settings: '設定',
    allianceOperations: '同盟運営',
    kingdomOperations: '王国運営',
    account: 'アカウント',
  },
  application: {
    dashboard: {
      title: 'ダッシュボード',
      eyebrow: '同盟司令部',
      welcome: 'ようこそ、{name}',
      verificationPending: 'メール認証待ち',
      playerContextTitle: 'アクティブプレイヤー',
      playerContextIntro:
        'プレイヤーを切り替えると、同盟と王国の権限に使用されるゲーム上の本人が変わります。',
      playerKingdom: '王国 #{kingdom}',
      playerAuthorityIntro:
        '同盟メンバーシップ、ランク、役割、王国権限、ゲーム操作はこのプレイヤーだけから判定されます。',
      selectPlayer: '総督を選択',
      playerAllianceTitle: 'アクティブプレイヤーの同盟',
      playerAllianceIntro:
        '同盟ツールは、アクティブプレイヤーのメンバーシップ、ランク、役割のみを使用します。',
      noPlayerAllianceTitle: 'このプレイヤーは同盟に所属していません',
      noPlayerAllianceIntro:
        '同盟ツールを開く前にプレイヤーを切り替えるか、アクティブプレイヤーで同盟を作成または参加してください。',
      openPlayerAlliance: 'プレイヤーの同盟を開く',
      active: 'アクティブ',
      roles: '役割',
      roster: '名簿',
      kingdomAlliances: '王国内同盟',
      transfers: '移転',
      kingdomSettings: '王国設定',
      createTitle: '同盟を作成',
      createIntro:
        'アクティブプレイヤーの同盟を作成します。同盟の王国はそのプレイヤーから決まり、そのプレイヤーが最初のR5になります。',
      allianceName: '同盟名',
      slug: 'スラッグ',
      timezone: 'タイムゾーン',
      create: '同盟を作成',
    },
  },
} satisfies MessageCatalogue;

export default messages;
