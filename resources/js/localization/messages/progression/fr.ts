import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Progression' },
  progression: {
    ...en.progression,
    title: 'Progression factuelle',
    eyebrow: 'Données de référence KingShot',
    subtitle:
      'Données de progression versionnées et sourcées. Les inconnues et conflits restent visibles au lieu d’être devinés.',
    factualOnly: 'Référence factuelle uniquement.',
    noRecommendations:
      'Les formations communautaires sont des conventions, pas des recommandations. Les calculateurs restent soumis au contrôle des preuves.',
    communityConvention: 'Convention communautaire',
    sourceConflicts: 'Conflits de sources',
    coverage: 'Couverture des données',
    sources: 'Sources et provenance',
  },
} satisfies MessageCatalogue;
export default messages;
