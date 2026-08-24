import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'ผู้ช่วย',
    title: 'ถาม Alliance ของคุณ',
    eyebrow: 'ผู้ช่วย Alliance · คำตอบตามสิทธิ์',
    subtitle:
      'ถามเกี่ยวกับ Event, roster ของคุณ, คู่มือ Alliance และข้อมูลสังเกตการณ์ คำตอบอ้างอิงเฉพาะแหล่งข้อมูลที่คุณมีสิทธิ์ดูอยู่แล้ว',
    authorizationHint: 'คำตอบใช้เฉพาะข้อมูล Alliance ที่คุณได้รับอนุญาตให้ดู',
    tryAsking: 'ลองถาม',
    conversation: 'การสนทนากับผู้ช่วย Alliance',
    youAsked: 'คุณถาม',
    possibleEvents: 'Event ที่เป็นไปได้',
    openEvent: 'เปิด Event',
    sourcesHeading: 'แหล่งข้อมูลที่ใช้',
    sourceTime: 'เวลาของแหล่งข้อมูล: {time}',
    questionLabel: 'ถาม Alliance ของคุณ',
    questionPlaceholder: 'Swordland เริ่มกี่โมงและฉันอยู่ใน roster ไหม?',
    inputHint: '{count}/{max} ตัวอักษร · Enter เพื่อถาม · Shift+Enter เพื่อขึ้นบรรทัดใหม่',
    asking: 'กำลังตรวจสอบแหล่งข้อมูล…',
    ask: 'ถาม',
    notRecorded: 'ยังไม่บันทึก',
    classifications: {
      operational_fact: 'ข้อเท็จจริงด้านปฏิบัติการ',
      game_fact: 'ข้อมูลเกม',
      alliance_strategy: 'กลยุทธ์ Alliance',
      observation: 'ข้อมูลสังเกตการณ์',
    },
    sources: {
      event: 'Event',
      roster: 'Roster',
      alliance_content: 'คู่มือ Alliance',
      observation: 'ข้อมูลสังเกตการณ์',
      game_fact: 'ข้อมูลเกม',
    },
    prompts: {
      swordland: 'Swordland เริ่มกี่โมงและฉันอยู่ใน roster ไหม?',
      nextEvent: 'Event ถัดไปของฉันคืออะไร?',
      bearGuide: 'คู่มือ Bear Hunt ของเราบอกว่าอย่างไร?',
      observation: 'เราสังเกตอะไรเกี่ยวกับคู่ต่อสู้บ้าง?',
    },
    answers: {
      help: 'ฉันตอบได้จาก Event, roster ของคุณ, คู่มือ Alliance และข้อมูลสังเกตการณ์ที่คุณมีสิทธิ์ดู โดยไม่ใช้ความรู้ KingShot ที่ไม่มีแหล่งอ้างอิง',
      unsupported:
        'ฉันตอบได้เฉพาะจาก Event ที่ได้รับอนุญาต, roster ของคุณ, คู่มือ Alliance และข้อมูลสังเกตการณ์ และไม่สามารถเปลี่ยนแปลงข้อมูลจากที่นี่ได้',
      unavailable: 'ขณะนี้ผู้ช่วย Alliance ตรวจสอบแหล่งข้อมูลไม่ได้ โปรดลองอีกครั้ง',
      rateLimited: 'คุณถามเร็วเกินไป โปรดลองอีกครั้งในอีกสักครู่',
      validationError: 'กรอกคำถามระหว่าง 2 ถึง {max} ตัวอักษร',
      noUpcomingEvent: 'ไม่พบ Event ที่กำลังจะมาถึงซึ่งคุณมีสิทธิ์ดู',
      eventSubjectMissing: 'ระบุ Event ที่ต้องการให้ตรวจสอบ',
      eventNotFound: 'ไม่พบ Event ที่ได้รับอนุญาตและกำลังจะมาถึงซึ่งตรงกับ “{subject}”',
      eventAmbiguous:
        'พบมากกว่าหนึ่ง Event ที่ตรงกับ “{subject}” โปรดเปิด Event ที่ต้องการด้านล่าง',
      eventTime: '{event} เริ่ม {startsAt}',
      eventTimeNotRostered: '{event} เริ่ม {startsAt} ขณะนี้คุณไม่ได้อยู่ใน roster',
      notRostered: 'ขณะนี้คุณไม่ได้อยู่ใน roster ของ {event}',
      eventTimeRostered:
        '{event} เริ่ม {startsAt} คุณอยู่ใน {roster} บทบาท: {role}; ช่อง: {slot}; สถานะ: {status}',
      rostered:
        'คุณอยู่ใน roster ของ {event} ใน {roster} บทบาท: {role}; ช่อง: {slot}; สถานะ: {status}',
      contentSubjectMissing: 'ระบุ Event หรือหัวข้อของคู่มือ Alliance ที่ต้องการตรวจสอบ',
      contentNotFound: 'ไม่พบเนื้อหา Alliance ที่เผยแพร่ซึ่งตรงกับ “{subject}”',
      contentFound: 'กลยุทธ์ Alliance — {title}: {excerpt}',
      observationSubjectMissing: 'ระบุ Alliance หรือหัวข้อสังเกตการณ์ที่ต้องการตรวจสอบ',
      observationNotFound: 'ไม่พบข้อมูลสังเกตการณ์ที่ได้รับอนุญาตซึ่งตรงกับ “{subject}”',
      observationFound: 'ข้อมูลสังเกตการณ์ — {title}: {observation}',
    },
  },
} satisfies MessageCatalogue;
export default messages;
