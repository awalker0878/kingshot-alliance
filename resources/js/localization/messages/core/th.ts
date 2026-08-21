import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'ภาษา',
    signIn: 'เข้าสู่ระบบ',
    signOut: 'ออกจากระบบ',
    createAccount: 'สร้างบัญชี',
    continue: 'ดำเนินการต่อ',
    cancel: 'ยกเลิก',
    save: 'บันทึก',
    close: 'ปิด',
    loading: 'กำลังโหลด',
    menu: 'เมนู',
    openNavigation: 'เปิดเมนูนำทาง',
    closeNavigation: 'ปิดเมนูนำทาง',
    playerAlliance: 'พันธมิตรของผู้ว่าการที่ใช้งานอยู่',
    noPlayerAlliance: 'ผู้ว่าการที่ใช้งานอยู่ไม่ได้อยู่ในพันธมิตรในขณะนี้',
    skipToContent: 'ข้ามไปยังเนื้อหา',
  },
  navigation: {
    home: 'หน้าหลัก',
    dashboard: 'ภาพรวมพันธมิตร',
    alliance: 'พันธมิตร',
    events: 'กิจกรรม',
    roster: 'สมาชิกพันธมิตร',
    recruitment: 'รับสมัคร',
    content: 'กระดานประกาศ',
    contributions: 'ผลงานของพันธมิตร',
    kingdom: 'พันธมิตรในอาณาจักร',
    transfers: 'ย้ายอาณาจักร',
    integrations: 'การเชื่อมต่อ',
    profile: 'บัญชีผู้ว่าการ',
    settings: 'การตั้งค่า',
    allianceOperations: 'พันธมิตร',
    kingdomOperations: 'อาณาจักร',
    account: 'บัญชีผู้ว่าการ',
  },
  application: {
    dashboard: {
      title: 'ภาพรวมพันธมิตร',
      eyebrow: 'พันธมิตรของคุณ',
      welcome: 'ยินดีต้อนรับ ผู้ว่าการ {name}',
      verificationPending: 'รอยืนยันอีเมล',
      playerContextTitle: 'ผู้ว่าการที่ใช้งานอยู่',
      playerContextIntro:
        'สลับผู้ว่าการเพื่อเปลี่ยนตัวตน Kingshot ที่ใช้สำหรับการดำเนินการของพันธมิตรและอาณาจักร',
      playerKingdom: 'อาณาจักร #{kingdom}',
      playerAuthorityIntro:
        'ยศพันธมิตร บทบาท หน้าที่ในอาณาจักร และการเข้าถึงกิจกรรมจะเป็นไปตามผู้ว่าการที่ใช้งานอยู่',
      selectPlayer: 'เลือกผู้ว่าการ',
      playerAllianceTitle: 'พันธมิตรของผู้ว่าการที่ใช้งานอยู่',
      playerAllianceIntro: 'การเข้าถึงพันธมิตรเป็นไปตามยศและบทบาทของผู้ว่าการที่ใช้งานอยู่',
      noPlayerAllianceTitle: 'ผู้ว่าการนี้ไม่ได้อยู่ในพันธมิตร',
      noPlayerAllianceIntro:
        'สลับผู้ว่าการ เข้าร่วมพันธมิตร หรือสร้างพันธมิตรเพื่อใช้ฟีเจอร์ของพันธมิตร',
      openPlayerAlliance: 'เปิดพันธมิตร',
      active: 'ใช้งานอยู่',
      roles: 'บทบาทพันธมิตร',
      roster: 'สมาชิกพันธมิตร',
      kingdomAlliances: 'พันธมิตรในอาณาจักร',
      transfers: 'ย้ายอาณาจักร',
      kingdomSettings: 'การตั้งค่าอาณาจักร',
      createTitle: 'สร้างพันธมิตร',
      createIntro:
        'สร้างพันธมิตรสำหรับผู้ว่าการที่ใช้งานอยู่ พันธมิตรจะใช้อาณาจักรของผู้ว่าการคนนั้น และผู้ว่าการผู้ก่อตั้งจะเป็น R5',
      allianceName: 'ชื่อพันธมิตร',
      timezone: 'เขตเวลาของพันธมิตร',
      create: 'สร้างพันธมิตร',
    },
  },
} satisfies MessageCatalogue;

export default messages;
