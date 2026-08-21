import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Ngôn ngữ',
    signIn: 'Đăng nhập',
    signOut: 'Đăng xuất',
    createAccount: 'Tạo tài khoản',
    continue: 'Tiếp tục',
    cancel: 'Hủy',
    save: 'Lưu',
    close: 'Đóng',
    loading: 'Đang tải',
    openNavigation: 'Mở điều hướng',
    closeNavigation: 'Đóng điều hướng',
    playerAlliance: 'Liên minh của Thống đốc đang hoạt động',
    noPlayerAlliance: 'Thống đốc đang hoạt động hiện không ở trong Liên minh.',
    skipToContent: 'Chuyển đến nội dung',
  },
  navigation: {
    home: 'Trang chủ',
    dashboard: 'Tổng quan Liên minh',
    alliance: 'Liên minh',
    events: 'Sự kiện',
    roster: 'Thành viên Liên minh',
    recruitment: 'Tuyển thành viên',
    content: 'Bảng thông báo',
    contributions: 'Đóng góp Liên minh',
    kingdom: 'Liên minh trong Vương quốc',
    transfers: 'Chuyển Vương quốc',
    integrations: 'Kết nối',
    profile: 'Tài khoản Thống đốc',
    settings: 'Cài đặt',
    allianceOperations: 'Liên minh',
    kingdomOperations: 'Vương quốc',
    account: 'Tài khoản Thống đốc',
  },
  application: {
    dashboard: {
      title: 'Tổng quan Liên minh',
      eyebrow: 'Liên minh của bạn',
      welcome: 'Chào mừng, Thống đốc {name}',
      verificationPending: 'Đang chờ xác minh email',
      playerContextTitle: 'Thống đốc đang hoạt động',
      playerContextIntro:
        'Đổi Thống đốc để thay đổi danh tính Kingshot dùng cho các hành động của Liên minh và Vương quốc.',
      playerKingdom: 'Vương quốc #{kingdom}',
      playerAuthorityIntro:
        'Cấp bậc Liên minh, vai trò, nhiệm vụ Vương quốc và quyền truy cập Sự kiện đi theo Thống đốc đang hoạt động.',
      selectPlayer: 'Chọn Thống đốc',
      playerAllianceTitle: 'Liên minh của Thống đốc đang hoạt động',
      playerAllianceIntro: 'Quyền truy cập Liên minh đi theo cấp bậc và vai trò của Thống đốc đang hoạt động.',
      noPlayerAllianceTitle: 'Thống đốc này không ở trong Liên minh',
      noPlayerAllianceIntro:
        'Đổi Thống đốc, tham gia Liên minh hoặc tạo Liên minh để sử dụng các tính năng Liên minh.',
      openPlayerAlliance: 'Mở Liên minh',
      active: 'Hoạt động',
      roles: 'Vai trò Liên minh',
      roster: 'Thành viên Liên minh',
      kingdomAlliances: 'Liên minh trong Vương quốc',
      transfers: 'Chuyển Vương quốc',
      kingdomSettings: 'Cài đặt Vương quốc',
      createTitle: 'Tạo Liên minh',
      createIntro:
        'Tạo Liên minh cho Thống đốc đang hoạt động. Liên minh sử dụng Vương quốc của Thống đốc đó và Thống đốc sáng lập trở thành R5.',
      allianceName: 'Tên Liên minh',
      timezone: 'Múi giờ của Liên minh',
      create: 'Tạo Liên minh',
    },
  },
} satisfies MessageCatalogue;

export default messages;
