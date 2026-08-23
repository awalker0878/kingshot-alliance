import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'Rozwój'}, progression:{...en.progression,title:'Rozwój oparty na faktach',eyebrow:'Dane referencyjne KingShot',subtitle:'Wersjonowane dane rozwoju z podanymi źródłami. Nieznane wartości i konflikty pozostają widoczne zamiast być zgadywane.',factualOnly:'Wyłącznie odniesienie faktograficzne.',noRecommendations:'Formacje społecznościowe są konwencjami, nie rekomendacjami. Kalkulatory nadal wymagają odpowiednich dowodów.',communityConvention:'Konwencja społeczności',sourceConflicts:'Konflikty źródeł',coverage:'Pokrycie danych',sources:'Źródła i pochodzenie'} } satisfies MessageCatalogue;
export default messages;
