import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'การเชื่อมต่อพันธมิตร',
    title: 'ข้อมูลรับรอง API และเว็บฮุก',
    activeCredentials: 'ข้อมูลรับรองที่ใช้งาน',
    activeWebhooks: 'เว็บฮุกที่ใช้งาน',
    recentDeliveries: 'การส่งล่าสุด',
    apiCredentials: 'ข้อมูลรับรอง API',
    createCredential: 'สร้างข้อมูลรับรอง',
    revoke: 'เพิกถอน',
    webhookSubscriptions: 'การสมัครเว็บฮุก',
    createWebhook: 'สร้างเว็บฮุก',
    deliveryLog: 'บันทึกการส่งล่าสุด',
    event: 'เหตุการณ์',
    status: 'สถานะ',
    attempts: 'จำนวนครั้ง',
    lastError: 'ข้อผิดพลาดล่าสุด',
  },
} satisfies MessageCatalogue;

export default messages;
