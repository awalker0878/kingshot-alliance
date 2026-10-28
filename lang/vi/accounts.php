<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Kiểm tra email Kingshot Alliance của anh.',
            'eyebrow' => 'KẾT QUẢ TƯ VẤN',
            'heading' => 'Xác minh email',
            'intro' => 'Dùng liên kết an toàn bên dưới để xác thực địa chỉ email cho tài khoản Kingshot Alliance của bạn.',
            'action' => 'Xác thực địa chỉ thư điện tử',
            'expiry' => 'Liên kết xác thực này hết hạn trong :minutes phút.',
            'notice' => 'Kingshot Alliance sẽ không bao giờ hỏi mật khẩu của bạn thông qua một thông điệp xác nhận email.',
        ],
        'email_change_verify' => [
            'subject' => 'Kiểm tra email mới của anh Kingshot Alliance',
            'eyebrow' => 'Kiểm tra an ninh',
            'heading' => 'Xác thực địa chỉ thư điện tử mới của bạn',
            'intro' => 'Dùng liên kết an toàn bên dưới để xác nhận địa chỉ email mới này cho tài khoản Kingshot Alliance của bạn. Thư điện tử hiện tại của bạn vẫn còn hoạt động cho đến khi xác thực thành công.',
            'action' => 'Xác thực địa chỉ thư mới',
            'expiry' => 'Liên kết xác thực này hết hạn trong :minutes phút.',
            'notice' => 'Nếu bạn không yêu cầu thay đổi này, đừng sử dụng liên kết này và xem lại bảo mật tài khoản của bạn.',
        ],
        'email_changed' => [
            'subject' => 'Email Kingshot Alliance của anh đã thay đổi.',
            'eyebrow' => 'Thông báo an ninh',
            'heading' => 'Thư điện tử của bạn đã được thay đổi',
            'intro' => 'Địa chỉ email cho tài khoản Kingshot Alliance của bạn đã được thay đổi thành :email.',
            'action' => 'Đăng nhập vào Kingshot Alliance',
            'notice' => 'Nếu bạn chưa thực hiện thay đổi này, hãy bảo vệ tài khoản của bạn và liên hệ với người quản trị trang.',
        ],
        'reset' => [
            'subject' => 'Đặt lại mật khẩu Kingshot Alliance của bạn',
            'eyebrow' => 'KHÓA KHÔNG THỂ ĐƯỢC LÀM LẠI',
            'heading' => 'Đặt lại mật khẩu của bạn',
            'intro' => 'Đã yêu cầu đặt lại mật khẩu cho tài khoản Kingshot Alliance của bạn. Dùng liên kết an toàn bên dưới nếu bạn đã yêu cầu điều này.',
            'action' => 'Đặt lại mật khẩu',
            'expiry' => 'Liên kết đặt lại mật khẩu này hết hạn trong :minutes phút.',
            'notice' => 'Nếu bạn không yêu cầu đặt lại mật khẩu, bạn có thể bỏ qua thông điệp này. Mật khẩu của anh sẽ không thay đổi.',
        ],
        'fallback' => 'Nếu nút này không hoạt động, sao chép và dán liên kết này vào trình duyệt:',
        'footer' => 'Kingshot Alliance là một ứng dụng quản lý liên minh độc lập của bên thứ ba cho người chơi Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Đã yêu cầu thay đổi địa chỉ thư',
            'body' => 'Một địa chỉ thư điện tử mới đang chờ xác nhận. Thư điện tử hiện tại của bạn vẫn còn hiệu lực cho đến khi địa chỉ mới được xác nhận.',
        ],
        'email_changed' => [
            'title' => 'Thư điện tử của tài khoản đã thay đổi',
            'body' => 'Thư điện tử tài khoản Kingshot Alliance của bạn đã được thay đổi sau khi xác nhận địa chỉ mới.',
        ],
        'password_changed' => [
            'title' => 'Mật khẩu đã thay đổi',
            'body' => 'Mật khẩu Kingshot Alliance của anh đã được thay đổi. Các phiên và mã truy cập khác bị vô hiệu hóa.',
        ],
        'password_added' => [
            'title' => 'Đã thêm mật khẩu đăng nhập',
            'body' => 'Một mật khẩu Kingshot Alliance đã được thêm vào như là phương pháp đăng nhập cho tài khoản của bạn.',
        ],
        'password_removed' => [
            'title' => 'Đã gỡ bỏ mật khẩu đăng nhập',
            'body' => 'Phương pháp đăng nhập mật khẩu Kingshot Alliance của bạn đã bị gỡ bỏ. Các tài liệu đặt lại mật khẩu hiện có không còn sử dụng được nữa.',
        ],
        'google_connected' => [
            'title' => 'Google đăng nhập kết nối',
            'body' => 'Một danh tính Google đã được kết nối như một phương pháp đăng nhập bổ sung cho tài khoản Kingshot Alliance của bạn.',
        ],
        'google_disconnected' => [
            'title' => 'Google đăng nhập bị cắt',
            'body' => 'Phương pháp đăng nhập Google đã được gỡ bỏ khỏi tài khoản Kingshot Alliance của bạn.',
        ],
        'passkey_added' => [
            'title' => 'Mật khẩu đã được thêm',
            'body' => 'Một mật khẩu mới đã được đăng ký như là phương pháp đăng nhập cho tài khoản Kingshot Alliance của bạn.',
        ],
        'passkey_renamed' => [
            'title' => 'Đổi tên khóa mật khẩu',
            'body' => 'Một nhãn mật khẩu đã được thay đổi cho tài khoản Kingshot Alliance của bạn.',
        ],
        'passkey_removed' => [
            'title' => 'Mật khẩu đã được gỡ bỏ',
            'body' => 'Một phương pháp đăng nhập mật khẩu đã được gỡ bỏ khỏi tài khoản Kingshot Alliance của bạn.',
        ],
        'mfa_enabled' => [
            'title' => 'Bật xác thực hai yếu tố',
            'body' => 'Giờ đã bật xác thực hai yếu tố dựa trên trình xác thực cho tài khoản Kingshot Alliance của bạn.',
        ],
        'mfa_disabled' => [
            'title' => 'Tắt xác thực hai yếu tố',
            'body' => 'Đã tắt xác thực hai yếu tố cho tài khoản Kingshot Alliance của bạn.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Mã phục hồi đã được tái tạo',
            'body' => 'Mã khôi phục hai yếu tố trước đây của bạn đã được thay thế và không thể sử dụng nữa.',
        ],
        'deletion_requested' => [
            'title' => 'Đã yêu cầu xóa tài khoản',
            'body' => 'Thời gian 7 ngày để xóa tài khoản Kingshot Alliance của anh đã bắt đầu.',
        ],
        'deletion_cancelled' => [
            'title' => 'Tạm dừng xóa tài khoản',
            'body' => 'Yêu cầu xóa đang chờ cho tài khoản Kingshot Alliance của anh đã bị hủy bỏ.',
        ],
    ],
];
