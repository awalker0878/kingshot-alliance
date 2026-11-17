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
      email: 'Email',
    },
    register: {
      title: 'Tạo tài khoản',
      name: 'Tên',
      password: 'Mật khẩu',
      passwordConfirmation: 'Xác nhận mật khẩu',
      submit: 'Tạo tài khoản',
      existingAccount: 'Đã có tài khoản?',
      email: 'Email',
    },
    password: {
      forgotTitle: 'Khôi phục tài khoản của bạn',
      forgotDescription: 'Nhập địa chỉ email liên kết với tài khoản Kingshot Alliance của bạn.',
      sendResetLink: 'Gửi hướng dẫn khôi phục',
      resetTitle: 'Chọn mật khẩu mới',
      resetSubmit: 'Đặt lại mật khẩu',
      confirmTitle: 'Xác nhận danh tính của bạn',
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
        'Tuyển mộ Thống đốc, chuẩn bị Sự kiện, quản lý Liên minh và theo dõi Vương quốc tại một nơi.',
    },
    login: {
      intro: 'Đăng nhập vào tài khoản, rồi chọn Thống đốc bạn muốn sử dụng.',
      invitationNotice:
        'Đăng nhập bằng tài khoản được mời để tiếp tục chấp nhận lời mời liên minh.',
      needAccount: 'Cần một tài khoản?',
      register: 'Đăng ký',
    },
    register: {
      intro:
        'Một tài khoản có thể sở hữu nhiều Thống đốc. Cấp bậc trong Liên minh và nhiệm vụ tại Vương quốc luôn thuộc về Thống đốc bạn chọn.',
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
      resetIntro: 'Chọn mật khẩu Kingshot Alliance mới cho tài khoản bên dưới.',
      newPassword: 'Mật khẩu mới',
      confirmNewPassword: 'Xác nhận mật khẩu mới',
      confirmDescription:
        'Thao tác này thay đổi quyền truy cập được bảo vệ vào Liên minh. Hãy xác nhận danh tính trước khi tiếp tục.',
      recoveryKicker: 'KHÔI PHỤC TÀI KHOẢN',
      recoveryNeutral:
        'Nếu địa chỉ email này có thể khôi phục mật khẩu, chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu an toàn.',
      checkInboxKicker: 'KIỂM TRA HỘP THƯ ĐẾN',
      checkInboxHeading: 'Đã yêu cầu hướng dẫn khôi phục',
      checkInboxDescription:
        'Nếu địa chỉ bạn nhập có thể khôi phục mật khẩu, hướng dẫn đặt lại mật khẩu đã được gửi. Vì lý do bảo mật, chúng tôi không thể xác nhận liệu tài khoản có tồn tại hay tài khoản sử dụng phương thức đăng nhập nào.',
      resetKicker: 'ĐẶT LẠI MẬT KHẨU AN TOÀN',
      accountEmail: 'Thư điện tử tài khoản',
      requirements: 'Mật khẩu của bạn phải bao gồm:',
      requirementLength: 'Ít nhất 12 ký tự',
      requirementUpper: 'Một chữ cái lớn',
      requirementLower: 'Một chữ cái nhỏ',
      requirementNumber: 'Một chữ số',
      showPassword: 'Hiện mật khẩu',
      hidePassword: 'Ẩn mật khẩu',
      validationSummary: 'Xem lại các ô tô đậm trước khi tiếp tục.',
    },
    verification: {
      description:
        'Chúng tôi đã gửi liên kết xác minh an toàn cho tài khoản Kingshot Alliance của bạn đến:',
      sent: 'Đã gửi liên kết xác minh mới.',
      kicker: 'XÁC MINH EMAIL',
      instructions: 'Mở tin nhắn và đi theo liên kết để hoàn thành xác thực địa chỉ email của bạn.',
      securityNote:
        'Kingshot Alliance sẽ không bao giờ yêu cầu mật khẩu của bạn qua thông báo xác minh email.',
      resendIn: 'Bạn có thể gửi lại sau {seconds} giây',
      useAnotherAccount: 'Thoát ra và sử dụng tài khoản khác',
    },
    twoFactor: {
      kicker: 'Bảo mật tài khoản',
      description: 'Nhập mã sáu chữ số hiện tại từ ứng dụng xác thực của bạn.',
      verifyCode: 'Xác minh mã',
      useRecoveryCode: 'Dùng mã khôi phục',
    },
    social: {
      continueWithGoogle: 'Tiếp tục với Google',
      createWithGoogle: 'Tạo tài khoản Kingshot Alliance bằng Google',
      or: 'hoặc',
    },
    passkeys: {
      signIn: 'Đăng nhập bằng khóa truy cập',
      authenticating: 'Đang kiểm tra khóa truy cập…',
    },
    confirm: {
      title: 'Xác nhận danh tính của bạn',
      description:
        'Sử dụng một trong các phương thức đăng nhập đã liên kết với tài khoản trước khi tiếp tục thay đổi nhạy cảm này.',
      usePassword: 'Kiểm tra mật khẩu',
      useGoogle: 'Kiểm tra với Google',
      usePasskey: 'Xác nhận bằng khóa truy cập',
    },
  },
} satisfies MessageCatalogue;

export default messages;
