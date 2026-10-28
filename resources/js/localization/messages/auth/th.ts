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
      recoveryKicker: 'คืนค่าบัญชี',
      recoveryNeutral: 'ถ้ามีรหัสผ่านที่เหมาะสมสำหรับอีเมล์นั้น เราจะส่งคำสั่งรีเซ็ตรหัสผ่านให้',
      checkInboxKicker: 'ตรวจสอบกล่องจดหมายของคุณ',
      checkInboxHeading: 'คำสั่งเรียกคืนถูกร้องขอ',
      checkInboxDescription:
        'หากมีข้อมูลที่ใช้ในการยืนยันตัวตนสำหรับที่อยู่ที่คุณป้อนไว้ คำสั่งตั้งค่ารหัสผ่านใหม่จะถูกส่งไปแล้ว สำหรับความปลอดภัย เราไม่สามารถยืนยันได้ว่าบัญชีนั้นมีอยู่จริง หรือว่ามันใช้วิธีการอะไร',
      resetKicker: 'เปลี่ยนรหัสผ่านให้ปลอดภัย',
      accountEmail: 'อีเมลของบัญชีผู้ใช้',
      requirements: 'รหัสผ่านของคุณต้องมี:',
      requirementLength: 'อย่างน้อย 12 อักขระ',
      requirementUpper: 'ตัวอักษรตัวใหญ่',
      requirementLower: 'ตัวอักษรตัวเล็ก',
      requirementNumber: 'ตัวเลข',
      showPassword: 'แสดงรหัสผ่าน',
      hidePassword: 'ซ่อนรหัสผ่าน',
      validationSummary: 'โปรดตรวจสอบช่องที่ถูกเน้นก่อนทำต่อไป',
    },
    verification: {
      description:
        'เราได้ส่งลิงก์ยืนยันไปยังอีเมลด้านล่าง โปรดยืนยันอีเมลก่อนดำเนินการบัญชีที่มีการป้องกัน',
      sent: 'ส่งลิงก์ยืนยันใหม่แล้ว',
      kicker: 'ตรวจสอบอีเมล',
      instructions: 'เปิดจดหมาย และคลิกที่ลิงก์ เพื่อยืนยันที่อยู่อีเมลของคุณให้เสร็จสมบูรณ์',
      securityNote: 'Kingshot Alliance จะไม่ถามรหัสผ่านของคุณผ่านทางอีเมลเพื่อยืนยัน',
      resendIn: 'นายจะส่งใหม่ได้ใน {seconds} วินาที',
      useAnotherAccount: 'ออกจากระบบและใช้บัญชีผู้ใช้อื่น',
    },
    twoFactor: {
      kicker: 'ตรวจสอบความปลอดภัย',
      description: 'กรอกรหัสหกหลักปัจจุบันจากแอปยืนยันตัวตนของคุณ',
      verifyCode: 'ยืนยันรหัส',
      useRecoveryCode: 'ใช้รหัสกู้คืน',
    },
    social: {
      continueWithGoogle: 'ต่อไปกับ Google',
      createWithGoogle: 'สร้างบัญชี Kingshot Alliance ด้วย Google',
      or: 'หรือ',
    },
    passkeys: {
      signIn: 'เข้าสู่ระบบด้วยพาสคีย์',
      authenticating: 'กำลังตรวจสอบพาสคีย์…',
    },
    confirm: {
      title: 'ยืนยันรหัสผ่าน',
      description:
        'โปรดใช้หนึ่งในวิธีการล็อกอินที่คุณได้ติดตั้งไว้ก่อนที่จะทำการเปลี่ยนแปลงบัญชีผู้ใช้ที่มีความลับนี้ต่อไป',
      usePassword: 'ยืนยันด้วยรหัสผ่าน',
      useGoogle: 'ยืนยันด้วย Google',
      usePasskey: 'ยืนยันด้วยพาสคีย์',
    },
  },
} satisfies MessageCatalogue;

export default messages;
