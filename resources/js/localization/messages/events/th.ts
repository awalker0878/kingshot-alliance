import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "ผู้เล่น",
      "alliance": "พันธมิตร",
      "kingdom": "อาณาจักร"
    },
    "actions": {
      "save": "บันทึก",
      "cancel": "ยกเลิก"
    },
    "calendar": {
      "title": "กิจกรรม",
      "create": "สร้างกิจกรรม",
      "agenda": "กำหนดการ",
      "month": "ปฏิทิน",
      "all": "ทุกขอบเขต",
      "manageable": "จัดการ",
      "empty": "ไม่มีกิจกรรมที่ตรงกับมุมมองนี้",
      "previousMonth": "เดือนก่อน",
      "nextMonth": "เดือนถัดไป",
      "scopeFilters": "กรองกิจกรรมตามขอบเขต",
      "viewOptions": "เลือกมุมมองกิจกรรม"
    },
    "create": {
      "title": "สร้างกิจกรรม",
      "back": "กลับไปยังกิจกรรม",
      "noContexts": "ขณะนี้คุณไม่มีสิทธิ์สร้างกิจกรรม",
      "context": "บริบทกิจกรรม",
      "eventType": "ประเภทกิจกรรม",
      "start": "เวลาเริ่ม",
      "duration": "ระยะเวลา (นาที)",
      "capacity": "ความจุ",
      "instructions": "คำแนะนำ",
      "submit": "สร้างกิจกรรม"
    },
    "show": {
      "back": "กลับไปยังกิจกรรม",
      "manage": "จัดการกิจกรรม",
      "details": "รายละเอียดกิจกรรม",
      "status": "สถานะ",
      "capacity": "ความจุ",
      "recurrence": "การเกิดซ้ำ",
      "modules": "โมดูลปฏิบัติการ"
    },
    "manage": {
      "title": "จัดการกิจกรรม",
      "back": "กลับไปยังกิจกรรม",
      "save": "บันทึกกิจกรรม",
      "cancel": "ยกเลิกกิจกรรม"
    },
    "attention": {
      "title": "การดำเนินการกิจกรรม",
      "response": "ต้องตอบกลับ",
      "registration": "เปิดลงทะเบียน",
      "vote": "ต้องโหวต",
      "roster_confirmation": "ต้องยืนยันรายชื่อ"
    },
    "reminders": {
      "title": "การแจ้งเตือนล่าสุด"
    },
    "participation": {
      "register": "ลงทะเบียน",
      "cancelRegistration": "ยกเลิกการลงทะเบียน"
    },
    "responses": {
      "going": "เข้าร่วม",
      "maybe": "อาจจะ",
      "unavailable": "ไม่ว่าง"
    },
    "registration": {
      "registered": "ลงทะเบียนแล้ว",
      "waitlisted": "รายชื่อรอ",
      "cancelled": "ยกเลิกแล้ว"
    },
    "scheduleSources": {
      "alliance_controlled": "ควบคุมโดยพันธมิตร",
      "game_calendar": "ปฏิทินเกม",
      "matchmaking": "จับคู่",
      "manual": "กำหนดเอง"
    },
    "recurrencePolicies": {
      "disabled": "ไม่เกิดซ้ำ",
      "fixed_interval": "ช่วงคงที่",
      "configurable": "กำหนดค่าได้"
    },
    "recurrenceFrequencies": {
      "none": "ไม่เกิดซ้ำ",
      "daily": "รายวัน",
      "weekly": "รายสัปดาห์"
    },
    "attendanceStatuses": {
      "present": "มา",
      "absent": "ขาด",
      "excused": "ลา",
      "unknown": "ไม่ทราบ"
    },
    "eventStatuses": {
      "draft": "ฉบับร่าง",
      "published": "เผยแพร่แล้ว",
      "cancelled": "ยกเลิกแล้ว",
      "completed": "เสร็จสิ้น"
    },
    "capabilities": {
      "responses": "การตอบกลับ",
      "registration": "ลงทะเบียน",
      "waitlist": "รายชื่อรอ",
      "attendance": "การเข้าร่วม",
      "phases": "เฟส",
      "polls": "โพล",
      "rosters": "รายชื่อ",
      "substitutes": "ตัวสำรอง",
      "teams": "ทีม",
      "legions": "กองทัพ",
      "rally_guidance": "คำแนะนำแรลลี่",
      "formations": "รูปขบวน",
      "objectives": "เป้าหมาย",
      "scoring": "คะแนน",
      "results": "ผลลัพธ์"
    },
    "reminderAudiences": {
      "target": "เป้าหมายกิจกรรม",
      "responded": "ผู้เล่นที่ตอบแล้ว",
      "registered": "ผู้เล่นที่ลงทะเบียน",
      "rostered": "ผู้เล่นในรายชื่อ",
      "all_scope_players": "ผู้เล่นที่มีสิทธิ์ทั้งหมด"
    }
  }
} satisfies MessageCatalogue;

export default messages;
