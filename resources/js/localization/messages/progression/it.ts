import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'Progressione'}, progression:{...en.progression,title:'Progressione fattuale',eyebrow:'Dati di riferimento KingShot',subtitle:'Dati di progressione versionati e con fonti. Valori sconosciuti e conflitti restano visibili invece di essere stimati.',factualOnly:'Solo riferimento fattuale.',noRecommendations:'Le formazioni della community sono convenzioni, non raccomandazioni. I calcolatori restano vincolati alle prove.',communityConvention:'Convenzione della community',sourceConflicts:'Conflitti tra fonti',coverage:'Copertura dei dati',sources:'Fonti e provenienza'} } satisfies MessageCatalogue;
export default messages;
