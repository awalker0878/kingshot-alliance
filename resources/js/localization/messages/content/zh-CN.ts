import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '本页 {count} 条记录 · 共 {total} 条',
    collectionFailed: '无法加载列表。请从第一页重试。',
    selectedUnavailable: '所选项目不可用',
    reviewOnThisPage: '此审核列表仅涵盖当前目录页。',
    allStatuses: '所有状态',
    retryCandidateSummary: '共 {total} 次失败尚未达到尝试上限，显示其中 {selected} 次供重试。',
    eyebrow: '联盟内容',
    hubTitle: '内容中心',
    manageContent: '管理内容',
    results: '结果',
    publicItems: '公开',
    memberItems: '仅成员',
    categories: '分类',
    search: '搜索联盟内容',
    type: '内容类型',
    category: '分类',
    locale: '语言',
    applyFilters: '应用筛选',
    clear: '清除',
    publishedContent: '已发布内容',
    publicProfile: '联盟公开资料',
    createContent: '创建内容',
    editContent: '编辑内容',
    mediaLibrary: '媒体库',
    contentInventory: '内容清单',
  },
} satisfies MessageCatalogue;

export default messages;
