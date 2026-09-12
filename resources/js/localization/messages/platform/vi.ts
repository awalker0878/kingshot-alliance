import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Lựa chọn đã chọn không còn khả dụng.',
    searchChoices: 'Tìm lựa chọn',
    choicePageSummary:
      '{count} lựa chọn trên trang; {total} kết quả phù hợp. Sắp xếp theo mã bản ghi ổn định.',
    choicesFailed: 'Không thể tải lựa chọn. Lựa chọn của bạn và trang đang xem được giữ nguyên.',
    retryChoices: 'Thử tải lại',
    recoveryFailed: 'Không thể hoàn tất khôi phục. Vui lòng thử lại.',
    recordCount: '{count} bản ghi',
    historyUnavailable: 'Không thể tải trang này.',
    retryPage: 'Thử tải lại trang',
    title: 'Quản trị nền tảng',
    backDashboard: 'Quay lại Trang chủ',
    localizationRuntime: 'Ngôn ngữ',
  },
} satisfies MessageCatalogue;

export default messages;
