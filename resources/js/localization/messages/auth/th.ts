import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'เข้าสู่ระบบ',
      email: 'อีเมล',
      password: 'รหัสผ่าน',
      remember: 'จดจำฉัน',
      forgotPassword: 'ลืมรหัสผ่าน?',
      submit: 'เข้าสู่ระบบ',
      createAccount: 'สร้างบัญชี',
      invitation: 'มีคำเชิญหรือไม่?',
    },
    register: {
      title: 'สร้างบัญชี',
      name: 'ชื่อ',
      email: 'อีเมล',
      password: 'รหัสผ่าน',
      passwordConfirmation: 'ยืนยันรหัสผ่าน',
      submit: 'สร้างบัญชี',
      existingAccount: 'มีบัญชีอยู่แล้ว?',
    },
    password: {
      forgotTitle: 'รีเซ็ตรหัสผ่าน',
      forgotDescription: 'กรอกอีเมลของคุณ แล้วเราจะส่งลิงก์รีเซ็ตรหัสผ่านให้',
      sendResetLink: 'ส่งลิงก์รีเซ็ต',
      resetTitle: 'เลือกรหัสผ่านใหม่',
      resetSubmit: 'รีเซ็ตรหัสผ่าน',
      confirmTitle: 'ยืนยันรหัสผ่าน',
    },
    verification: {
      title: 'ยืนยันอีเมลของคุณ',
      resend: 'ส่งอีเมลยืนยันอีกครั้ง',
    },
    twoFactor: {
      title: 'การยืนยันตัวตนสองขั้นตอน',
      code: 'รหัสยืนยัน',
      recoveryCode: 'รหัสกู้คืน',
      submit: 'ดำเนินการต่อ',
    },
    invitation: {
      title: 'คำเชิญเข้าพันธมิตร',
      accept: 'ยอมรับคำเชิญ',
    },
  },
  authExperience: {
    shell: {
      headline: 'สร้างมาเพื่อผู้นำพันธมิตร',
      intro:
        'เข้าถึงเครื่องมือที่พันธมิตรใช้ประสานงาน รับสมัคร และเตรียมพร้อมสำหรับสิ่งต่อไปอย่างปลอดภัย',
    },
    login: {
      intro: 'เข้าถึงพันธมิตรทั้งหมดที่เชื่อมกับบัญชีส่วนกลางของคุณ',
      invitationNotice: 'เข้าสู่ระบบด้วยบัญชีที่ได้รับเชิญเพื่อดำเนินการตอบรับคำเชิญพันธมิตรต่อ',
      needAccount: 'ต้องการบัญชีหรือไม่?',
      register: 'สมัครบัญชี',
    },
    register: {
      intro: 'ตัวตนส่วนกลางหนึ่งบัญชีสามารถอยู่ในหลายพันธมิตรได้',
      invitationNotice:
        'คุณได้รับเชิญเข้า {alliance} ด้วย {email} การสร้างบัญชีจะยอมรับคำเชิญนี้ด้วย',
      invitationOnly: 'ขณะนี้สมัครได้ด้วยคำเชิญเท่านั้น โปรดเปิดลิงก์คำเชิญที่พันธมิตรส่งมา',
      timezone: 'เขตเวลา',
      passwordHint: 'อย่างน้อย 12 ตัวอักษร พร้อมตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข',
      existingAccount: 'มีบัญชีอยู่แล้ว?',
    },
    invitation: {
      join: 'เข้าร่วม {alliance}',
      forEmail: 'คำเชิญนี้สำหรับ {email}',
      expires: 'หมดอายุ {date}',
      wrongAccount:
        'คุณเข้าสู่ระบบเป็น {email} โปรดเข้าสู่ระบบด้วยอีเมลที่ได้รับเชิญเพื่อยอมรับคำเชิญนี้',
      createAndJoin: 'สร้างบัญชีและเข้าร่วม',
      signInAccept: 'เข้าสู่ระบบเพื่อยอมรับ',
    },
    password: {
      backToSignIn: 'กลับไปเข้าสู่ระบบ',
      resetIntro: 'การรีเซ็ตรหัสผ่านจะยกเลิกโทเค็นการเข้าถึงส่วนบุคคล',
      newPassword: 'รหัสผ่านใหม่',
      confirmNewPassword: 'ยืนยันรหัสผ่านใหม่',
      confirmDescription:
        'การดำเนินการนี้เปลี่ยนการเข้าถึงหรือสิทธิ์ของพันธมิตร จึงต้องยืนยันรหัสผ่านอีกครั้ง',
    },
    verification: {
      description:
        'เราได้ส่งลิงก์ยืนยันไปที่ {email} โปรดยืนยันอีเมลก่อนดำเนินการบัญชีที่มีการป้องกัน',
      sent: 'ส่งลิงก์ยืนยันใหม่แล้ว',
    },
    twoFactor: {
      kicker: 'ตรวจสอบความปลอดภัย',
      description: 'กรอกรหัสหกหลักปัจจุบันจากแอปยืนยันตัวตนของคุณ',
      verifyCode: 'ยืนยันรหัส',
      useRecoveryCode: 'ใช้รหัสกู้คืน',
    },
  },
} satisfies MessageCatalogue;

export default messages;
