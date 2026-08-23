import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'สรุป Bear Hunt',
    eyebrow: 'Bear Hunt · หลังจบกิจกรรม',
    subtitle:
      'ตรวจสอบดาเมจที่บันทึก การเข้าร่วม การร่วม Rally Governor ที่ยังจับคู่ไม่ได้ และเปรียบเทียบกับการล่าครั้งล่าสุด',
    totalDamage: 'ดาเมจรวม',
    governors: 'Governor',
    governor: 'Governor',
    governorCount: '{count} Governor',
    attendance: 'การเข้าร่วม',
    recordedRallies: 'Rally ที่บันทึก',
    notRecorded: 'ยังไม่ได้บันทึก',
    notComparable: 'ไม่มีข้อมูลเปรียบเทียบ',
    noChange: 'ไม่เปลี่ยนจากครั้งก่อน',
    increased: 'เพิ่มขึ้น',
    decreased: 'ลดลง',
    changeWithPercent: '{direction} {amount} ({percent}%) เทียบกับครั้งก่อน',
    change: '{direction} {amount} เทียบกับครั้งก่อน',
    rankUp: 'อันดับดีขึ้น {count} ตำแหน่ง',
    rankDown: 'อันดับลดลง {count} ตำแหน่ง',
    yourHunt: 'การล่าของคุณ',
    damage: 'ดาเมจ',
    rank: 'อันดับ',
    alliancePerformance: 'ผลงาน Alliance',
    leaderboard: 'อันดับ Governor',
    reportCount: 'รายงานการรบที่บันทึก {count} รายการ',
    unknownGovernor: 'ไม่ทราบ Governor',
    noResults: 'ยังไม่มีดาเมจของ Governor ที่บันทึกสำหรับการล่าครั้งนี้',
    needsReview: 'ต้องตรวจสอบ',
    unmatchedGovernors: 'มี {count} Governor ที่ต้องจับคู่',
    reviewHelp:
      'จับคู่ Governor ให้เสร็จใน Screenshot Intake หน้าสรุปจะไม่สร้างขั้นตอนระบุตัวตนซ้ำ',
    reviewImport: 'ตรวจสอบรายงานที่นำเข้า',
    trends: 'แนวโน้ม',
    runTrends: 'แนวโน้ม Bear Hunt ล่าสุด',
    yourDamageTrend: 'ดาเมจของคุณในแต่ละครั้ง',
    allianceDamageTrend: 'ดาเมจ Alliance ในแต่ละครั้ง',
    previousHunt: 'ครั้งก่อน',
    noPrevious: 'ไม่มีครั้งก่อน',
    noPreviousHelp:
      'การเปรียบเทียบจะแสดงเมื่อ Alliance นี้มี Bear Hunt ก่อนหน้าที่เสร็จสิ้นแล้ว',
    history: 'ประวัติ',
    runHistory: 'ประวัติ Bear Hunt',
  },
} satisfies MessageCatalogue;

export default messages;
