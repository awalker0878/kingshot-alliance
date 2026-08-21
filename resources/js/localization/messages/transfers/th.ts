import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
