import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Tuyển thành viên liên minh',
    title: 'Tuyển thành viên',
    candidates: 'Ứng viên',
    accepted: 'Đã chấp nhận',
    joined: 'Đã tham gia',
    pipeline: 'Tuyển thành viên',
    backToPipeline: 'Quay lại tuyển thành viên',
    stage: 'Giai đoạn',
    source: 'Nguồn',
    submitted: 'Đã gửi',
    nextAction: 'Hành động tiếp theo',
    bulkActions: 'Thay đổi giai đoạn ứng viên',
    selectedCandidates: 'Đã chọn {count} ứng viên',
    bulkPreviewHelp: 'Kiểm tra ai có thể chuyển giai đoạn trước khi áp dụng thay đổi. Ứng viên không đủ điều kiện sẽ không thay đổi.',
    previewBulkAction: 'Kiểm tra thay đổi giai đoạn',
    bulkPreview: 'Xem trước thay đổi giai đoạn',
    bulkPreviewSummary: '{ready} có thể cập nhật và {blocked} cần xem lại hoặc đã ở giai đoạn đích.',
    confirmBulkTitle: 'Xác nhận thay đổi giai đoạn',
    confirmBulkDescription: 'Chuyển {count} ứng viên đủ điều kiện sang {stage}?',
    confirmBulkAction: 'Cập nhật ứng viên đủ điều kiện',
    bulkResult: 'Kết quả thay đổi giai đoạn',
    bulkResultSummary: '{succeeded} đã cập nhật. {failed} cần xem lại. {skipped} đã ở trạng thái mới nhất.',
    failedItemsSelected: 'Ứng viên không thể cập nhật vẫn được chọn để bạn xem lại.',
    settings: 'Cài đặt ứng tuyển',
    questions: 'Câu hỏi ứng tuyển',
    onboarding: 'Danh sách hội nhập',
    choosePlayer: 'Chọn Thống đốc',
    privateNotes: 'Ghi chú riêng của tuyển dụng',
    stageHistory: 'Lịch sử giai đoạn',
  },
} satisfies MessageCatalogue;

export default messages;
