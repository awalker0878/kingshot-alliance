import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: '進行状況' },
  progression: {
    ...en.progression,
    title: '事実ベースの進行データ',
    eyebrow: 'KingShot 参照データ',
    subtitle: 'バージョンと出典を持つ進行データです。不明点や競合は推測せず、そのまま表示します。',
    factualOnly: '事実参照のみ。',
    noRecommendations:
      'コミュニティ編成は慣例であり、推奨ではありません。計算機能は引き続き証拠ゲートの対象です。',
    communityConvention: 'コミュニティ慣例',
    sourceConflicts: '出典の競合',
    coverage: 'データ範囲',
    sources: '出典と来歴',
  },
} satisfies MessageCatalogue;
export default messages;
