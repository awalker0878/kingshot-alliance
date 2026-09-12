import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: '所选选项已不可用。',
    searchChoices: '搜索选项',
    choicePageSummary: '本页 {count} 个选项，共 {total} 个匹配项。按固定记录标识排序。',
    choicesFailed: '无法加载选项。当前选择和显示的页面保持不变。',
    retryChoices: '重试加载',
    recoveryFailed: '无法完成恢复。请重试。',
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
