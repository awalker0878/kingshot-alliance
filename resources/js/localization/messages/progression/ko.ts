import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'성장'}, progression:{...en.progression,title:'사실 기반 성장 데이터',eyebrow:'KingShot 참조 데이터',subtitle:'버전과 출처가 명확한 성장 데이터입니다. 모르는 값과 충돌은 추측하지 않고 그대로 표시합니다.',factualOnly:'사실 참조 전용.',noRecommendations:'커뮤니티 편성은 관행이지 추천이 아닙니다. 계산기는 계속 근거 게이트를 통과해야 합니다.',communityConvention:'커뮤니티 관행',sourceConflicts:'출처 충돌',coverage:'데이터 범위',sources:'출처 및 계보'} } satisfies MessageCatalogue;
export default messages;
