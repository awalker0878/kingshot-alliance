import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'การแจ้งเตือนสิทธิประโยชน์ตำแหน่ง',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'การจัดการบัญชี',
      title: 'บัญชีและความปลอดภัย',
      intro: 'จัดการตัวตน การยืนยัน อีเมล รหัสผ่าน การยืนยันสองขั้นตอน และเซสชันที่ใช้งานอยู่',
      passwordUpdated: 'อัปเดตรหัสผ่านแล้วและออกจากเซสชันที่ยืนยันตัวตนอื่น ๆ',
      sessionsRevoked: 'ออกจากเซสชันที่ยืนยันตัวตนอื่นแล้ว',
      twoFactorDisabled: 'ปิดการยืนยันสองขั้นตอนแล้ว',
      profileTitle: 'โปรไฟล์',
      profileIntro: 'การเปลี่ยนอีเมลต้องยืนยันใหม่',
      timezone: 'เขตเวลา',
      saveProfile: 'บันทึกโปรไฟล์',
      emailVerification: 'การยืนยันอีเมล',
      verified: 'ยืนยันแล้ว',
      pending: 'รอดำเนินการ',
      twoFactorState: 'การยืนยันสองขั้นตอน',
      enabled: 'เปิดใช้งาน',
      setupPending: 'รอการตั้งค่า',
      notEnabled: 'ยังไม่เปิด',
      twoFactorTitle: 'การยืนยันสองขั้นตอน',
      twoFactorIntro:
        'ปกป้องการเข้าสู่ระบบด้วยแอปยืนยันตัวตน รหัสกู้คืนจะแสดงเฉพาะตอนสร้างหรือสร้างใหม่',
      startSetup: 'เริ่มตั้งค่า',
      authenticatorSecret: 'รหัสลับของแอปยืนยันตัวตน',
      provisioningUri: 'URI การตั้งค่า',
      authenticationCode: 'รหัสยืนยันตัวตน',
      confirm: 'ยืนยัน',
      saveRecoveryCodes: 'บันทึกรหัสกู้คืนเหล่านี้ตอนนี้',
      recoveryIntro: 'แต่ละรหัสใช้ได้หนึ่งครั้ง เก็บไว้แยกจากบัญชีนี้',
      regenerateRecoveryCodes: 'สร้างรหัสกู้คืนใหม่',
      disableTwoFactor: 'ปิดการยืนยันสองขั้นตอน',
      passwordTitle: 'เปลี่ยนรหัสผ่าน',
      passwordIntro: 'การเปลี่ยนรหัสผ่านจะออกจากอุปกรณ์อื่นและปิดการเข้าถึงที่ยังใช้งานอยู่อื่น ๆ',
      currentPassword: 'รหัสผ่านปัจจุบัน',
      newPassword: 'รหัสผ่านใหม่',
      confirmNewPassword: 'ยืนยันรหัสผ่านใหม่',
      updatePassword: 'อัปเดตรหัสผ่าน',
      sessionsTitle: 'เซสชันอื่น',
      sessionsIntro: 'ออกจากทุกเซสชันที่ยืนยันตัวตนยกเว้นอุปกรณ์นี้',
      signOutOthers: 'ออกจากอุปกรณ์อื่น',
      dangerTitle: 'พื้นที่เสี่ยง',
      deleteAccount: 'ลบบัญชี',
    },
    deletion: {
      eyebrow: 'วงจรบัญชี',
      title: 'ลบบัญชี',
      intro:
        'การลบมีช่วงรอเจ็ดวัน การเป็นเจ้าของพันธมิตรที่ใช้งานอยู่ สิทธิ์ผู้ดูแลแพลตฟอร์ม และข้อกำหนดทางกฎหมายอาจขัดขวางการดำเนินการ บัญชีที่ประมวลผลจะถูกทำให้ไม่ระบุตัวตนโดยไม่ลบประวัติการตรวจสอบ',
      currentRequest: 'คำขอปัจจุบัน',
      status: 'สถานะ',
      eligibleAt: 'ดำเนินการได้เมื่อ',
      requestedAt: 'ขอเมื่อ',
      processedAt: 'ประมวลผลเมื่อ',
      notYet: 'ยังไม่',
      requestTitle: 'ขอลบบัญชี',
      requestIntro:
        'โอนความเป็นเจ้าของพันธมิตรที่คุณถือครองก่อน ระเบียนที่อยู่ภายใต้ข้อกำหนดทางกฎหมายหรือจำเป็นต่อความปลอดภัยและการตรวจสอบจะถูกเก็บแบบนามแฝง',
      requestButton: 'ขอลบบัญชี',
      confirm:
        'ต้องการขอลบบัญชีหรือไม่? มีช่วงรอเจ็ดวันและการตรวจสอบความเป็นเจ้าของ/ข้อกำหนดทางกฎหมาย',
      requested: 'บันทึกคำขอลบบัญชีแล้ว',
      backToAccount: 'กลับไปบัญชีและความปลอดภัย',
    },
  },
} satisfies MessageCatalogue;

export default messages;
