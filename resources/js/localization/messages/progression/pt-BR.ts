import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Progressão' },
  progression: {
    ...en.progression,
    title: 'Progressão factual',
    eyebrow: 'Dados de referência do KingShot',
    subtitle:
      'Dados de progressão versionados e com fontes. Lacunas e conflitos continuam visíveis em vez de serem presumidos.',
    factualOnly: 'Somente referência factual.',
    noRecommendations:
      'Formações da comunidade são convenções, não recomendações. Calculadoras continuam bloqueadas por evidências.',
    communityConvention: 'Convenção da comunidade',
    sourceConflicts: 'Conflitos de fontes',
    coverage: 'Cobertura dos dados',
    sources: 'Fontes e proveniência',
  },
} satisfies MessageCatalogue;
export default messages;
