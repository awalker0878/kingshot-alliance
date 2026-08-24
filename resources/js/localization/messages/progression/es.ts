import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Progresión' },
  progression: {
    ...en.progression,
    title: 'Progresión factual',
    eyebrow: 'Datos de referencia de KingShot',
    subtitle:
      'Datos de progresión versionados y con fuentes. Las incógnitas y los conflictos permanecen visibles en lugar de adivinarse.',
    factualOnly: 'Solo referencia factual.',
    noRecommendations:
      'Las formaciones de la comunidad son convenciones, no recomendaciones. Las calculadoras siguen sujetas a la evidencia.',
    communityConvention: 'Convención de la comunidad',
    sourceConflicts: 'Conflictos de fuentes',
    coverage: 'Cobertura del conjunto de datos',
    sources: 'Fuentes y procedencia',
  },
} satisfies MessageCatalogue;
export default messages;
