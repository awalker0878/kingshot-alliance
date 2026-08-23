import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'成長'}, progression:{...en.progression,title:'事實成長資料',eyebrow:'KingShot 參考資料',subtitle:'具版本與來源的成長資料。未知值與來源衝突會明確顯示，而不是用猜測補上。',factualOnly:'僅提供事實參考。',noRecommendations:'社群兵種配比只是慣例，不是推薦。計算器仍需通過證據門檻。',communityConvention:'社群慣例',sourceConflicts:'來源衝突',coverage:'資料覆蓋',sources:'來源與溯源'} } satisfies MessageCatalogue;
export default messages;
