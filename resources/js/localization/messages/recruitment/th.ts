import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'การรับสมาชิกพันธมิตร',
    title: 'การรับสมัคร',
    candidates: 'ผู้สมัคร',
    accepted: 'รับแล้ว',
    joined: 'เข้าร่วมแล้ว',
    pipeline: 'การรับสมัคร',
    backToPipeline: 'กลับไปที่การรับสมัคร',
    stage: 'ขั้นตอน',
    source: 'ที่มา',
    submitted: 'ส่งเมื่อ',
    nextAction: 'การดำเนินการถัดไป',
    bulkActions: 'เปลี่ยนขั้นตอนผู้สมัคร',
    selectedCandidates: 'เลือกผู้สมัคร {count} คน',
    bulkPreviewHelp: 'ตรวจสอบว่าใครสามารถย้ายขั้นตอนได้ก่อนใช้การเปลี่ยนแปลง ผู้สมัครที่ไม่เข้าเกณฑ์จะไม่ถูกเปลี่ยน',
    previewBulkAction: 'ตรวจสอบการเปลี่ยนขั้นตอน',
    bulkPreview: 'ตัวอย่างการเปลี่ยนขั้นตอน',
    bulkPreviewSummary: '{ready} คนอัปเดตได้ และ {blocked} คนต้องตรวจสอบหรืออยู่ในขั้นตอนเป้าหมายแล้ว',
    confirmBulkTitle: 'ยืนยันการเปลี่ยนขั้นตอน',
    confirmBulkDescription: 'ย้ายผู้สมัครที่เข้าเกณฑ์ {count} คนไปยัง {stage} หรือไม่?',
    confirmBulkAction: 'อัปเดตผู้สมัครที่เข้าเกณฑ์',
    bulkResult: 'ผลการเปลี่ยนขั้นตอน',
    bulkResultSummary: 'อัปเดตแล้ว {succeeded} คน ต้องตรวจสอบ {failed} คน และ {skipped} คนเป็นปัจจุบันอยู่แล้ว',
    failedItemsSelected: 'ผู้สมัครที่อัปเดตไม่ได้จะยังคงถูกเลือกไว้เพื่อให้ตรวจสอบได้',
    settings: 'การตั้งค่าใบสมัคร',
    questions: 'คำถามใบสมัคร',
    onboarding: 'รายการเริ่มต้นสมาชิก',
    choosePlayer: 'เลือกผู้ว่าการ',
    privateNotes: 'บันทึกส่วนตัวของผู้รับสมัคร',
    stageHistory: 'ประวัติขั้นตอน',
  },
} satisfies MessageCatalogue;

export default messages;
