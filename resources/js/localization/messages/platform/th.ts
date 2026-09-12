import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'ตัวเลือกที่เลือกไม่พร้อมใช้งานแล้ว',
    searchChoices: 'ค้นหาตัวเลือก',
    choicePageSummary:
      'หน้านี้มี {count} ตัวเลือก ตรงกันทั้งหมด {total} รายการ เรียงตามรหัสระเบียนคงที่',
    choicesFailed: 'โหลดตัวเลือกไม่สำเร็จ ตัวเลือกที่เลือกและหน้าที่แสดงยังคงเดิม',
    retryChoices: 'ลองโหลดอีกครั้ง',
    recoveryFailed: 'ไม่สามารถกู้คืนให้เสร็จสมบูรณ์ได้ โปรดลองอีกครั้ง',
    recordCount: '{count} รายการ',
    historyUnavailable: 'ไม่สามารถโหลดหน้านี้ได้',
    retryPage: 'ลองโหลดหน้าอีกครั้ง',
    title: 'การดูแลแพลตฟอร์ม',
    backDashboard: 'กลับหน้าหลัก',
    localizationRuntime: 'ภาษา',
  },
} satisfies MessageCatalogue;

export default messages;
