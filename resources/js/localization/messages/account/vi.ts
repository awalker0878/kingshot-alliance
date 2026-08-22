import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Nhắc nhở Đặc quyền Vị trí',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Quản lý tài khoản',
      title: 'Tài khoản & bảo mật',
      intro:
        'Quản lý danh tính, xác minh, mật khẩu, xác thực hai yếu tố và các phiên đang hoạt động.',
      passwordUpdated: 'Mật khẩu đã được cập nhật và các phiên đã xác thực khác đã đăng xuất.',
      sessionsRevoked: 'Các phiên đã xác thực khác đã đăng xuất.',
      twoFactorDisabled: 'Xác thực hai yếu tố đã bị tắt.',
      profileTitle: 'Hồ sơ',
      profileIntro: 'Thay đổi email yêu cầu xác minh lại.',
      timezone: 'Múi giờ',
      saveProfile: 'Lưu hồ sơ',
      emailVerification: 'Xác minh email',
      verified: 'Đã xác minh',
      pending: 'Đang chờ',
      twoFactorState: 'Xác thực hai yếu tố',
      enabled: 'Đã bật',
      setupPending: 'Đang chờ thiết lập',
      notEnabled: 'Chưa bật',
      twoFactorTitle: 'Xác thực hai yếu tố',
      twoFactorIntro:
        'Bảo vệ đăng nhập bằng ứng dụng xác thực. Mã khôi phục chỉ hiển thị khi được tạo hoặc tạo lại.',
      startSetup: 'Bắt đầu thiết lập',
      authenticatorSecret: 'Mã bí mật xác thực',
      provisioningUri: 'URI cấp cấu hình',
      authenticationCode: 'Mã xác thực',
      confirm: 'Xác nhận',
      saveRecoveryCodes: 'Lưu các mã khôi phục này ngay',
      recoveryIntro: 'Mỗi mã chỉ dùng một lần. Hãy lưu chúng tách biệt khỏi tài khoản này.',
      regenerateRecoveryCodes: 'Tạo lại mã khôi phục',
      disableTwoFactor: 'Tắt xác thực hai yếu tố',
      passwordTitle: 'Đổi mật khẩu',
      passwordIntro:
        'Đổi mật khẩu sẽ đăng xuất các thiết bị khác và đóng các quyền truy cập đang hoạt động khác.',
      currentPassword: 'Mật khẩu hiện tại',
      newPassword: 'Mật khẩu mới',
      confirmNewPassword: 'Xác nhận mật khẩu mới',
      updatePassword: 'Cập nhật mật khẩu',
      sessionsTitle: 'Phiên khác',
      sessionsIntro: 'Đăng xuất mọi phiên đã xác thực ngoại trừ thiết bị này.',
      signOutOthers: 'Đăng xuất thiết bị khác',
      dangerTitle: 'Khu vực nguy hiểm',
      deleteAccount: 'Xóa tài khoản',
    },
    deletion: {
      eyebrow: 'Vòng đời tài khoản',
      title: 'Xóa tài khoản',
      intro:
        'Việc xóa có thời gian chờ bảy ngày. Quyền sở hữu liên minh đang hoạt động, quyền quản trị nền tảng và yêu cầu lưu giữ pháp lý có thể chặn xử lý. Tài khoản đã xử lý được ẩn danh thay vì xóa lịch sử kiểm toán.',
      currentRequest: 'Yêu cầu hiện tại',
      status: 'Trạng thái',
      eligibleAt: 'Đủ điều kiện lúc',
      requestedAt: 'Yêu cầu lúc',
      processedAt: 'Xử lý lúc',
      notYet: 'Chưa',
      requestTitle: 'Yêu cầu xóa',
      requestIntro:
        'Trước tiên hãy chuyển quyền sở hữu mọi liên minh. Hồ sơ thuộc diện lưu giữ pháp lý hoặc cần cho bảo mật/kiểm toán sẽ được giữ ở dạng giả danh.',
      requestButton: 'Yêu cầu xóa tài khoản',
      confirm:
        'Yêu cầu xóa tài khoản? Có thời gian chờ bảy ngày và kiểm tra quyền sở hữu/lưu giữ pháp lý.',
      requested: 'Yêu cầu xóa tài khoản đã được ghi nhận.',
      backToAccount: 'Quay lại tài khoản & bảo mật',
    },
  },
} satisfies MessageCatalogue;

export default messages;
