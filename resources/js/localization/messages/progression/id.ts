import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Progresi' },
  progression: {
    ...en.progression,
    title: 'Progresi faktual',
    eyebrow: 'Data referensi KingShot',
    subtitle:
      'Data progresi berversi dan bersumber. Nilai yang tidak diketahui dan konflik tetap terlihat, bukan ditebak.',
    factualOnly: 'Hanya referensi faktual.',
    noRecommendations:
      'Formasi komunitas adalah konvensi, bukan rekomendasi. Kalkulator tetap dibatasi oleh gerbang bukti.',
    communityConvention: 'Konvensi komunitas',
    sourceConflicts: 'Konflik sumber',
    coverage: 'Cakupan data',
    sources: 'Sumber dan asal data',
  },
} satisfies MessageCatalogue;
export default messages;
