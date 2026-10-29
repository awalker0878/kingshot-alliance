import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'Tổng kết Bear Hunt',
    eyebrow: 'Bear Hunt · Sau sự kiện',
    subtitle:
      'Xem sát thương đã ghi nhận, điểm danh, tham gia Rally, Governor chưa khớp và so sánh với các lần săn gần đây.',
    totalDamage: 'Tổng sát thương',
    governors: 'Governor',
    governor: 'Thống đốc',
    governorCount: '{count} Governor',
    attendance: 'Điểm danh',
    recordedRallies: 'Rally đã ghi nhận',
    notRecorded: 'Chưa ghi nhận',
    notComparable: 'Không có dữ liệu so sánh',
    noChange: 'Không đổi so với lần săn trước',
    increased: 'tăng',
    decreased: 'giảm',
    changeWithPercent: '{direction} {amount} ({percent}%) so với lần săn trước',
    change: '{direction} {amount} so với lần săn trước',
    rankUp: 'Tăng {count} hạng so với lần săn trước',
    rankDown: 'Giảm {count} hạng so với lần săn trước',
    yourHunt: 'Lần săn của bạn',
    damage: 'Sát thương',
    rank: 'Hạng',
    alliancePerformance: 'Hiệu suất Alliance',
    leaderboard: 'Bảng xếp hạng Governor',
    reportCount: '{count} báo cáo chiến đấu đã ghi nhận',
    unknownGovernor: 'Governor không xác định',
    noResults: 'Chưa có sát thương Governor nào được ghi nhận cho lần săn này.',
    needsReview: 'Cần xem lại',
    unmatchedGovernors: '{count} Governor cần được khớp',
    reviewHelp:
      'Hoàn tất khớp Governor trong Screenshot Intake. Trang tổng kết không tạo luồng nhận dạng thứ hai.',
    reviewImport: 'Xem báo cáo đã nhập',
    trends: 'Xu hướng',
    runTrends: 'Xu hướng Bear Hunt gần đây',
    yourDamageTrend: 'Sát thương của bạn theo lần săn',
    allianceDamageTrend: 'Sát thương Alliance theo lần săn',
    previousHunt: 'Lần săn trước',
    noPrevious: 'Không có lần săn trước',
    noPreviousHelp:
      'So sánh sẽ xuất hiện khi Alliance này có một Bear Hunt trước đó đã hoàn thành.',
    history: 'Lịch sử',
    runHistory: 'Lịch sử Bear Hunt',
    signals: {
      evidence: 'Trạng thái bằng chứng',
      factualSignals: 'So sánh ghi lại',
      reviewPending: 'Một hoặc nhiều hàng thống đốc nhập khẩu vẫn cần xem xét danh tính.',
      newPersonalBest: 'Kỷ lục cá nhân mới từ kết quả chấp nhận',
      damageDirection: 'Thương vong {direction}',
      rallyDirection: 'Đại hội tham gia {direction}',
      evidenceStates: {
        accepted: 'Kết quả chụp màn hình chấp nhận',
        recorded_without_accepted_evidence: 'Kết quả ghi lại không có bằng chứng được chấp nhận',
        unavailable: 'Không ghi lại kết quả',
      },
      directions: {
        unknown: 'không so sánh được',
        increased: 'tăng',
        decreased: 'giảm',
        unchanged: 'không thay đổi',
      },
    },
  },
} satisfies MessageCatalogue;

export default messages;
