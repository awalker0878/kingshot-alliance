import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Đăng nhập',
      password: 'Mật khẩu',
      remember: 'Ghi nhớ tôi',
      forgotPassword: 'Quên mật khẩu?',
      submit: 'Đăng nhập',
      createAccount: 'Tạo tài khoản',
      invitation: 'Bạn có lời mời?',
    },
    register: {
      title: 'Tạo tài khoản',
      name: 'Tên',
      password: 'Mật khẩu',
      passwordConfirmation: 'Xác nhận mật khẩu',
      submit: 'Tạo tài khoản',
      existingAccount: 'Đã có tài khoản?',
    },
    password: {
      forgotTitle: 'Đặt lại mật khẩu',
      forgotDescription: 'Nhập địa chỉ email và chúng tôi sẽ gửi liên kết đặt lại mật khẩu.',
      sendResetLink: 'Gửi liên kết đặt lại',
      resetTitle: 'Chọn mật khẩu mới',
      resetSubmit: 'Đặt lại mật khẩu',
      confirmTitle: 'Xác nhận mật khẩu',
    },
    verification: {
      title: 'Xác minh email',
      resend: 'Gửi lại email xác minh',
    },
    twoFactor: {
      title: 'Xác thực hai yếu tố',
      code: 'Mã xác thực',
      recoveryCode: 'Mã khôi phục',
      submit: 'Tiếp tục',
    },
    invitation: {
      title: 'Lời mời liên minh',
      accept: 'Chấp nhận lời mời',
    },
  },
  authExperience: {
    shell: {
      headline: 'Được xây dựng cho lãnh đạo liên minh.',
      intro:
        'Truy cập an toàn vào các công cụ liên minh dùng để phối hợp, tuyển thành viên và chuẩn bị cho bước tiếp theo.',
    },
    login: {
      intro: 'Truy cập mọi liên minh được liên kết với tài khoản toàn cục của bạn.',
      invitationNotice:
        'Đăng nhập bằng tài khoản được mời để tiếp tục chấp nhận lời mời liên minh.',
      needAccount: 'Cần một tài khoản?',
      register: 'Đăng ký',
    },
    register: {
      intro: 'Một danh tính toàn cục có thể thuộc nhiều liên minh.',
      invitationNotice:
        'Bạn được mời vào {alliance} với địa chỉ {email}. Tạo tài khoản cũng sẽ chấp nhận lời mời này.',
      invitationOnly:
        'Hiện tại chỉ có thể đăng ký bằng lời mời. Hãy mở liên kết lời mời do liên minh gửi.',
      timezone: 'Múi giờ',
      passwordHint: 'Ít nhất 12 ký tự, có chữ hoa, chữ thường và số.',
      existingAccount: 'Đã có tài khoản?',
    },
    invitation: {
      join: 'Tham gia {alliance}',
      forEmail: 'Lời mời này dành cho {email}.',
      expires: 'Hết hạn {date}',
      wrongAccount:
        'Bạn đang đăng nhập bằng {email}. Hãy đăng nhập bằng email được mời để chấp nhận lời mời này.',
      createAndJoin: 'Tạo tài khoản và tham gia',
      signInAccept: 'Đăng nhập để chấp nhận',
    },
    password: {
      backToSignIn: 'Quay lại đăng nhập',
      resetIntro: 'Đặt lại mật khẩu sẽ thu hồi các mã truy cập cá nhân.',
      newPassword: 'Mật khẩu mới',
      confirmNewPassword: 'Xác nhận mật khẩu mới',
      confirmDescription:
        'Hành động này thay đổi quyền truy cập hoặc quyền hạn của liên minh, vì vậy bạn phải xác nhận lại mật khẩu.',
    },
    verification: {
      description:
        'Chúng tôi đã gửi liên kết xác minh đến {email}. Hãy xác minh địa chỉ trước các thao tác tài khoản được bảo vệ.',
      sent: 'Đã gửi liên kết xác minh mới.',
    },
    twoFactor: {
      kicker: 'Kiểm tra bảo mật',
      description: 'Nhập mã sáu chữ số hiện tại từ ứng dụng xác thực của bạn.',
      verifyCode: 'Xác minh mã',
      useRecoveryCode: 'Dùng mã khôi phục',
    },
  },
} satisfies MessageCatalogue;

export default messages;
