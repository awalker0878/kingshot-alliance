import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Tích hợp liên minh',
    title: 'Thông tin API và webhook',
    activeCredentials: 'Thông tin xác thực đang hoạt động',
    activeWebhooks: 'Webhook đang hoạt động',
    recentDeliveries: 'Lần gửi gần đây',
    apiCredentials: 'Thông tin xác thực API',
    createCredential: 'Tạo thông tin xác thực',
    revoke: 'Thu hồi',
    webhookSubscriptions: 'Đăng ký webhook',
    createWebhook: 'Tạo webhook',
    deliveryLog: 'Nhật ký gửi gần đây',
    event: 'Sự kiện',
    status: 'Trạng thái',
    attempts: 'Lần thử',
    lastError: 'Lỗi gần nhất',
  },
} satisfies MessageCatalogue;

export default messages;
