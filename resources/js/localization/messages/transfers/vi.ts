import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Tìm lựa chọn',
    choicePageSummary:
      '{count} lựa chọn trên trang; {total} kết quả phù hợp. Sắp xếp theo mã bản ghi ổn định.',
    choiceUnavailable: 'Lựa chọn đã chọn không còn khả dụng.',
    choicesFailed: 'Không thể tải lựa chọn. Lựa chọn của bạn và trang đang xem được giữ nguyên.',
    retryChoices: 'Thử tải lại',

    participantPagination: 'Trang người tham gia',
    participantPageSummary: '{count} người tham gia trên trang này; {total} trong chế độ xem này.',
    participantPageOrder: 'Sắp xếp theo mã đăng ký cố định. Tổng số bao gồm toàn bộ chế độ xem.',
    participantPageUnavailable:
      'Không thể tải trang người tham gia. Trang đang hiển thị không thay đổi.',
    retryParticipantPage: 'Thử tải lại',
    participantFilterPageOnly:
      'Bộ lọc điều kiện này áp dụng cho trang hiện tại. Chuyển qua các trang để xem tất cả người tham gia.',
    workflowHistoryUnavailable: 'Không thể tải lịch sử quy trình chuyển.',
    workflowHistoryTotal: 'Có {count} bản ghi trong lịch sử này.',
    observationHistoryUnavailable: 'Không thể tải lịch sử quan sát.',
    reloadHistory: 'Tải lại lịch sử',
    eyebrow: 'Chuyển Vương quốc',
    title: 'Lập kế hoạch chuyển',
    readinessBoard: 'Chuẩn bị',
    completion: 'Kết quả',
    manageTransfers: 'Quản lý chuyển',
    currentCycle: 'Chu kỳ hiện tại',
    participants: 'Người tham gia',
    incoming: 'Chuyển vào',
    outgoing: 'Chuyển ra',
    staying: 'Ở lại',
    transferGroups: 'Nhóm chuyển',
    player: 'Thống đốc',
    gamePlayerId: 'ID game của Thống đốc',
    readinessTitle: 'Chuẩn bị chuyển',
    completionTitle: 'Kết quả chuyển',
    recordCompletion: 'Ghi kết quả chuyển',
    rosterHandoffRecorded: 'Danh sách liên minh đã cập nhật',
    completedStatus: 'Hoàn tất',
    notCompletedStatus: 'Chưa hoàn tất',
    readinessReady: 'Sẵn sàng',
    readinessBlocked: 'Bị chặn',
    readinessConfirmed: 'Đã xác nhận',
  },
} satisfies MessageCatalogue;

export default messages;
