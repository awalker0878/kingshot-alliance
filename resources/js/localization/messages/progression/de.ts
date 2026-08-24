import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Fortschritt' },
  progression: {
    ...en.progression,
    title: 'Faktischer Fortschritt',
    eyebrow: 'KingShot-Referenzdaten',
    subtitle:
      'Versionierte Fortschrittsdaten mit Quellen. Unbekannte Werte und Konflikte bleiben sichtbar, statt geschätzt zu werden.',
    factualOnly: 'Nur faktische Referenz.',
    noRecommendations:
      'Community-Formationen sind Konventionen, keine Empfehlungen. Rechner bleiben evidenzgebunden.',
    communityConvention: 'Community-Konvention',
    sourceConflicts: 'Quellenkonflikte',
    coverage: 'Datenabdeckung',
    sources: 'Quellen und Herkunft',
  },
} satisfies MessageCatalogue;
export default messages;
