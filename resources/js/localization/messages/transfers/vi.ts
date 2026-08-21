import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
