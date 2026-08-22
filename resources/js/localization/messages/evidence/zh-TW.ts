import type { MessageCatalogue } from '../../types';
const messages = { evidence: { openIntake: '匯入戰鬥報告', eyebrow: '獵熊 · 證據審核', title: '截圖匯入', subtitle: '上傳獵熊戰鬥報告，並在變更活動結果前審核每個擷取值。', back: '返回獵熊', uploadTitle: '上傳戰鬥報告', uploadHelp: '支援 JPEG、PNG 或 WebP。原圖保持私密，經過安全掃描並產生不可變校驗值。', chooseFile: '戰鬥報告截圖', upload: '上傳截圖', uploading: '正在上傳…', existingTitle: '本次獵熊的證據', empty: '本次獵熊尚未上傳截圖。', originalName: '來源', status: '狀態', received: '已接收', security: '來源資訊' } } satisfies MessageCatalogue;
export default messages;
