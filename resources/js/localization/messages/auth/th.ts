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
      forgotTitle: 'กู้คืนบัญชีของคุณ',
      forgotDescription: 'ป้อนที่อยู่อีเมลที่เชื่อมโยงกับบัญชี Kingshot Alliance ของคุณ',
      sendResetLink: 'ส่งคำแนะนำในการกู้คืน',
      resetTitle: 'เลือกรหัสผ่านใหม่',
      resetSubmit: 'รีเซ็ตรหัสผ่าน',
      confirmTitle: 'ยืนยันตัวตนของคุณ',
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
        'รับสมัครผู้ว่าการ เตรียมกิจกรรม จัดการพันธมิตร และติดตามความเคลื่อนไหวของอาณาจักรได้ในที่เดียว',
    },
    login: {
      intro: 'ลงชื่อเข้าใช้บัญชีของคุณ แล้วเลือกผู้ว่าการที่ต้องการใช้งาน',
      invitationNotice: 'เข้าสู่ระบบด้วยบัญชีที่ได้รับเชิญเพื่อดำเนินการตอบรับคำเชิญพันธมิตรต่อ',
      needAccount: 'ต้องการบัญชีหรือไม่?',
      register: 'สมัครบัญชี',
    },
    register: {
      intro:
        'หนึ่งบัญชีสามารถมีผู้ว่าการได้หลายคน ยศในพันธมิตรและหน้าที่ในอาณาจักรจะอิงตามผู้ว่าการที่คุณเลือกเสมอ',
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
      resetIntro: 'เลือกรหัสผ่าน Kingshot Alliance ใหม่สำหรับบัญชีด้านล่าง',
      newPassword: 'รหัสผ่านใหม่',
      confirmNewPassword: 'ยืนยันรหัสผ่านใหม่',
      confirmDescription:
        'การดำเนินการนี้จะเปลี่ยนสิทธิ์การเข้าถึงพันธมิตรที่มีการป้องกัน โปรดยืนยันตัวตนก่อนดำเนินการต่อ',
      recoveryKicker: 'การกู้คืนบัญชี',
      recoveryNeutral:
        'หากที่อยู่อีเมลนี้สามารถใช้กู้คืนรหัสผ่านได้ เราจะส่งคำแนะนำในการตั้งรหัสผ่านใหม่อย่างปลอดภัย',
      checkInboxKicker: 'ตรวจสอบกล่องจดหมายเข้า',
      checkInboxHeading: 'ส่งคำขอคำแนะนำในการกู้คืนแล้ว',
      checkInboxDescription:
        'หากที่อยู่อีเมลที่คุณป้อนสามารถใช้กู้คืนรหัสผ่านได้ เราได้ส่งคำแนะนำในการตั้งรหัสผ่านใหม่แล้ว ด้วยเหตุผลด้านความปลอดภัย เราไม่สามารถยืนยันได้ว่ามีบัญชีนั้นอยู่หรือบัญชีใช้วิธีลงชื่อเข้าใช้ใด',
      resetKicker: 'ตั้งรหัสผ่านใหม่อย่างปลอดภัย',
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
      description: 'เราได้ส่งลิงก์ยืนยันที่ปลอดภัยสำหรับบัญชี Kingshot Alliance ของคุณไปที่:',
      sent: 'ส่งลิงก์ยืนยันใหม่แล้ว',
      kicker: 'การยืนยันอีเมล',
      instructions: 'เปิดจดหมาย และคลิกที่ลิงก์ เพื่อยืนยันที่อยู่อีเมลของคุณให้เสร็จสมบูรณ์',
      securityNote: 'Kingshot Alliance จะไม่ขอรหัสผ่านของคุณผ่านข้อความยืนยันอีเมลเด็ดขาด',
      resendIn: 'ส่งซ้ำได้ในอีก {seconds} วินาที',
      useAnotherAccount: 'ออกจากระบบและใช้บัญชีผู้ใช้อื่น',
    },
    twoFactor: {
      kicker: 'ความปลอดภัยของบัญชี',
      description: 'กรอกรหัสหกหลักปัจจุบันจากแอปยืนยันตัวตนของคุณ',
      verifyCode: 'ยืนยันรหัส',
      useRecoveryCode: 'ใช้รหัสกู้คืน',
    },
    social: {
      continueWithGoogle: 'ดำเนินการต่อด้วย Google',
      createWithGoogle: 'สร้างบัญชี Kingshot Alliance ด้วย Google',
      or: 'หรือ',
    },
    passkeys: {
      signIn: 'เข้าสู่ระบบด้วยพาสคีย์',
      authenticating: 'กำลังตรวจสอบพาสคีย์…',
    },
    confirm: {
      title: 'ยืนยันตัวตนของคุณ',
      description:
        'ใช้วิธีลงชื่อเข้าใช้ที่เชื่อมโยงกับบัญชีของคุณวิธีใดวิธีหนึ่ง ก่อนดำเนินการเปลี่ยนแปลงบัญชีที่เกี่ยวข้องกับความปลอดภัยนี้ต่อ',
      usePassword: 'ยืนยันด้วยรหัสผ่าน',
      useGoogle: 'ยืนยันด้วย Google',
      usePasskey: 'ยืนยันด้วยพาสคีย์',
    },
  },
} satisfies MessageCatalogue;

export default messages;
