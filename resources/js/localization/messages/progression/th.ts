import type { MessageCatalogue } from '../../types';
import en from './en';
const messages = { ...en, navigation:{...en.navigation, progression:'ความก้าวหน้า'}, progression:{...en.progression,title:'ข้อมูลความก้าวหน้าเชิงข้อเท็จจริง',eyebrow:'ข้อมูลอ้างอิง KingShot',subtitle:'ข้อมูลความก้าวหน้าที่มีเวอร์ชันและแหล่งที่มา ค่าที่ยังไม่ทราบและข้อขัดแย้งจะแสดงไว้อย่างชัดเจนแทนการคาดเดา',factualOnly:'ข้อมูลอ้างอิงเชิงข้อเท็จจริงเท่านั้น',noRecommendations:'รูปแบบกองทัพจากชุมชนเป็นเพียงแนวปฏิบัติ ไม่ใช่คำแนะนำ เครื่องคำนวณยังต้องผ่านเกณฑ์หลักฐาน',communityConvention:'แนวปฏิบัติของชุมชน',sourceConflicts:'ข้อขัดแย้งของแหล่งข้อมูล',coverage:'ความครอบคลุมของข้อมูล',sources:'แหล่งข้อมูลและที่มา'} } satisfies MessageCatalogue;
export default messages;
