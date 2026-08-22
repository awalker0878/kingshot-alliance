import type { MessageCatalogue } from '../../types';
const messages = { evidence: { openIntake: '导入战斗报告', eyebrow: '猎熊 · 证据审核', title: '截图导入', subtitle: '上传猎熊战斗报告，并在更改活动结果前审核每个提取值。', back: '返回猎熊', uploadTitle: '上传战斗报告', uploadHelp: '支持 JPEG、PNG 或 WebP。原图保持私密，经过安全扫描并生成不可变校验值。', chooseFile: '战斗报告截图', upload: '上传截图', uploading: '正在上传…', existingTitle: '本次猎熊的证据', empty: '本次猎熊尚未上传截图。', originalName: '来源', status: '状态', received: '已接收', security: '来源信息' } } satisfies MessageCatalogue;
export default messages;
