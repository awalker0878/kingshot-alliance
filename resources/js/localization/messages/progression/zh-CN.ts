import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: '成长' },
  progression: {
    ...en.progression,
    title: '事实成长数据',
    eyebrow: 'KingShot 参考数据',
    subtitle: '带版本和来源的成长数据。未知值和来源冲突会明确显示，而不是用猜测填补。',
    factualOnly: '仅提供事实参考。',
    noRecommendations: '社区兵种配比只是惯例，不是推荐。计算器仍需通过证据门槛。',
    communityConvention: '社区惯例',
    sourceConflicts: '来源冲突',
    coverage: '数据覆盖',
    sources: '来源与溯源',
  },
} satisfies MessageCatalogue;
export default messages;
