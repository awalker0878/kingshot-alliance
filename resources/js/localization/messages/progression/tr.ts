import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'İlerleme' },
  progression: {
    ...en.progression,
    title: 'Olgusal İlerleme',
    eyebrow: 'KingShot referans verileri',
    subtitle:
      'Sürüm ve kaynak bilgisi olan ilerleme verileri. Bilinmeyenler ve çelişkiler tahmin edilmek yerine görünür kalır.',
    factualOnly: 'Yalnızca olgusal referans.',
    noRecommendations:
      'Topluluk dizilimleri birer gelenektir, öneri değildir. Hesaplayıcılar kanıt eşiğine bağlı kalır.',
    communityConvention: 'Topluluk geleneği',
    sourceConflicts: 'Kaynak çelişkileri',
    coverage: 'Veri kapsamı',
    sources: 'Kaynaklar ve köken',
  },
} satisfies MessageCatalogue;
export default messages;
