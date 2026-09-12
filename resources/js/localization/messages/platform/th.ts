import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} รายการ',
    historyUnavailable: 'ไม่สามารถโหลดหน้านี้ได้',
    retryPage: 'ลองโหลดหน้าอีกครั้ง',
    title: 'การดูแลแพลตฟอร์ม',
    backDashboard: 'กลับหน้าหลัก',
    localizationRuntime: 'ภาษา',
  },
} satisfies MessageCatalogue;

export default messages;
