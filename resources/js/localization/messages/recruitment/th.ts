import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'การรับสมาชิกพันธมิตร',
    title: 'การรับสมัคร',
    candidates: 'ผู้สมัคร',
    accepted: 'รับแล้ว',
    joined: 'เข้าร่วมแล้ว',
    pipeline: 'ขั้นตอนผู้สมัคร',
    stage: 'ขั้นตอน',
    source: 'ที่มา',
    submitted: 'ส่งเมื่อ',
    nextAction: 'การดำเนินการถัดไป',
    settings: 'การตั้งค่าใบสมัคร',
    questions: 'คำถามใบสมัคร',
    onboarding: 'รายการเริ่มต้นสมาชิก',
    privateNotes: 'บันทึกส่วนตัวของผู้รับสมัคร',
    stageHistory: 'ประวัติขั้นตอน',
  },
} satisfies MessageCatalogue;

export default messages;
