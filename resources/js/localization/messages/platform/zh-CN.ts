import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} 条记录',
    historyUnavailable: '无法加载此页面。',
    retryPage: '重试此页',
    title: '平台管理',
    backDashboard: '返回首页',
    administrators: '平台管理员',
    allianceFleet: '联盟',
    localizationRuntime: '语言',
  },
} satisfies MessageCatalogue;

export default messages;
