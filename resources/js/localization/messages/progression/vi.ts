import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = {
  ...en,
  navigation: { ...en.navigation, progression: 'Tiến triển' },
  progression: {
    ...en.progression,
    title: 'Tiến triển theo dữ kiện',
    eyebrow: 'Dữ liệu tham chiếu KingShot',
    subtitle:
      'Dữ liệu tiến triển có phiên bản và nguồn. Phần chưa biết và mâu thuẫn được hiển thị thay vì suy đoán.',
    factualOnly: 'Chỉ tham chiếu dữ kiện.',
    noRecommendations:
      'Đội hình cộng đồng là quy ước, không phải khuyến nghị. Máy tính vẫn bị chặn bởi cổng bằng chứng.',
    communityConvention: 'Quy ước cộng đồng',
    sourceConflicts: 'Xung đột nguồn',
    coverage: 'Độ phủ dữ liệu',
    sources: 'Nguồn và xuất xứ',
  },
} satisfies MessageCatalogue;
export default messages;
