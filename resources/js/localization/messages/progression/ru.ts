import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'Развитие'}, progression:{...en.progression,title:'Фактическое развитие',eyebrow:'Справочные данные KingShot',subtitle:'Версионированные данные развития с источниками. Неизвестные значения и конфликты показываются, а не угадываются.',factualOnly:'Только фактическая справка.',noRecommendations:'Формации сообщества — это соглашения, а не рекомендации. Калькуляторы по-прежнему требуют достаточных доказательств.',communityConvention:'Соглашение сообщества',sourceConflicts:'Конфликты источников',coverage:'Покрытие данных',sources:'Источники и происхождение'} } satisfies MessageCatalogue;
export default messages;
