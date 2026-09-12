import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'ค้นหาตัวเลือก',
    choicePageSummary:
      'หน้านี้มี {count} ตัวเลือก ตรงกันทั้งหมด {total} รายการ เรียงตามรหัสระเบียนคงที่',
    choiceUnavailable: 'ตัวเลือกที่เลือกไม่พร้อมใช้งานแล้ว',
    choicesFailed: 'โหลดตัวเลือกไม่สำเร็จ ตัวเลือกที่เลือกและหน้าที่แสดงยังคงเดิม',
    retryChoices: 'ลองโหลดอีกครั้ง',

    participantPagination: 'หน้าผู้เข้าร่วม',
    participantPageSummary: 'หน้านี้มีผู้เข้าร่วม {count} คน; มุมมองนี้มีทั้งหมด {total} คน',
    participantPageOrder: 'เรียงตามรหัสลงทะเบียนที่คงที่ ยอดรวมครอบคลุมทั้งมุมมอง',
    participantPageUnavailable: 'ไม่สามารถโหลดหน้าผู้เข้าร่วมได้ หน้าที่แสดงอยู่ยังไม่เปลี่ยนแปลง',
    retryParticipantPage: 'ลองโหลดหน้าอีกครั้ง',
    participantFilterPageOnly:
      'ตัวกรองคุณสมบัตินี้ใช้กับหน้าปัจจุบัน โปรดเลื่อนผ่านหน้าต่าง ๆ เพื่อตรวจสอบผู้เข้าร่วมทั้งหมด',
    workflowHistoryUnavailable: 'ไม่สามารถโหลดประวัติกระบวนการย้ายได้',
    workflowHistoryTotal: 'มี {count} รายการในประวัตินี้',
    observationHistoryUnavailable: 'ไม่สามารถโหลดประวัติการสังเกตได้',
    reloadHistory: 'โหลดประวัติใหม่',
    eyebrow: 'การย้ายอาณาจักร',
    title: 'การวางแผนย้าย',
    readinessBoard: 'ความพร้อม',
    completion: 'ผลลัพธ์',
    manageTransfers: 'จัดการการย้าย',
    currentCycle: 'รอบปัจจุบัน',
    participants: 'ผู้เข้าร่วม',
    incoming: 'ย้ายเข้า',
    outgoing: 'ย้ายออก',
    staying: 'อยู่ต่อ',
    transferGroups: 'กลุ่มการย้าย',
    player: 'ผู้ว่าการ',
    gamePlayerId: 'ID เกมของผู้ว่าการ',
    readinessTitle: 'ความพร้อมในการย้าย',
    completionTitle: 'ผลการย้าย',
    recordCompletion: 'บันทึกผลการย้าย',
    rosterHandoffRecorded: 'อัปเดตรายชื่อพันธมิตรแล้ว',
    completedStatus: 'เสร็จสิ้น',
    notCompletedStatus: 'ยังไม่เสร็จ',
    readinessReady: 'พร้อม',
    readinessBlocked: 'ติดขัด',
    readinessConfirmed: 'ยืนยันแล้ว',
  },
} satisfies MessageCatalogue;

export default messages;
