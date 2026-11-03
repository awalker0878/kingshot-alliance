<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Kingshot Alliance 이메일 확인',
            'eyebrow' => '이메일 확인',
            'heading' => '이메일 확인',
            'intro' => '아래의 안전한 링크를 사용하여 Kingshot Alliance 계정의 이메일 주소를 확인하십시오.',
            'action' => '이메일 주소 확인',
            'expiry' => '이 확인 링크는 :minutes분 후에 만료됩니다.',
            'notice' => 'Kingshot Alliance은 이메일 확인 메시지를 통해 절대로 비밀번호를 요청하지 않습니다.',
        ],
        'email_change_verify' => [
            'subject' => '새로운 Kingshot Alliance 이메일을 확인하십시오.',
            'eyebrow' => '보안 검증',
            'heading' => '새 이메일 주소 확인',
            'intro' => '아래의 안전한 링크를 사용하여 Kingshot Alliance 계정의 새 이메일 주소를 확인하십시오. 현재 이메일은 인증이 성공할 때까지 활성 상태로 유지됩니다.',
            'action' => '새 이메일 주소 확인',
            'expiry' => '이 확인 링크는 :minutes분 후에 만료됩니다.',
            'notice' => '이 변경을 요청하지 않은 경우 이 링크를 사용하지 마시고 계정 보안을 검토하십시오.',
        ],
        'email_changed' => [
            'subject' => 'Kingshot Alliance 이메일이 변경되었습니다.',
            'eyebrow' => '보안 공지 사항',
            'heading' => '귀하의 계정 이메일이 변경되었습니다',
            'intro' => 'Kingshot Alliance 계정의 이메일 주소가 :email로 변경되었습니다.',
            'action' => 'Kingshot Alliance에 로그인',
            'notice' => '이 변경을 하지 않은 경우 계정을 보호하고 사이트 관리자에게 문의하십시오.',
        ],
        'reset' => [
            'subject' => 'Kingshot Alliance 비밀번호 재설정',
            'eyebrow' => '계정 복구',
            'heading' => '비밀번호 재설정',
            'intro' => 'Kingshot Alliance 계정에 대한 비밀번호 재설정이 요청되었습니다. 이 요청을 한 경우 아래의 안전한 링크를 사용합니다.',
            'action' => '비밀번호 재설정',
            'expiry' => '이 비밀번호 재설정 링크는 :minutes분 후에 만료됩니다.',
            'notice' => '비밀번호 재설정을 요청하지 않은 경우 이 메시지를 무시할 수 있습니다. 비밀번호는 변경되지 않습니다.',
        ],
        'fallback' => '버튼이 작동하지 않으면, 이 링크를 복사하여 브라우저에 붙여넣으십시오.',
        'footer' => 'Kingshot Alliance은 Kingshot 플레이어를 위한 독립적인 타사 동맹 관리 응용 프로그램입니다.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => '이메일 변경 요청',
            'body' => '새로운 이메일 주소가 확인을 기다리고 있습니다. 새로운 주소가 확인될 때까지 현재 이메일 주소는 활성 상태로 유지됩니다.',
        ],
        'email_changed' => [
            'title' => '계정 이메일 변경',
            'body' => 'Kingshot Alliance 계정 이메일이 새 주소 확인 후 변경되었습니다.',
        ],
        'password_changed' => [
            'title' => '비밀번호가 변경되었습니다',
            'body' => 'Kingshot Alliance 비밀번호가 변경되었습니다. 다른 세션과 액세스 토큰은 무효화되었습니다.',
        ],
        'password_added' => [
            'title' => '암호 로그인 추가',
            'body' => 'Kingshot Alliance 암호가 계정의 로그인 방법으로 추가되었습니다.',
        ],
        'password_removed' => [
            'title' => '비밀번호 로그인 제거됨',
            'body' => 'Kingshot Alliance 암호 로그인 방법이 제거되었습니다. 기존 비밀번호 재설정 자료는 더 이상 사용할 수 없습니다.',
        ],
        'google_connected' => [
            'title' => 'Google 로그인 연결됨',
            'body' => 'Google ID가 Kingshot Alliance 계정의 추가 로그인 방법으로 연결되었습니다.',
        ],
        'google_disconnected' => [
            'title' => 'Google 로그인 연결이 끊겼습니다.',
            'body' => 'Google 로그인 방법이 Kingshot Alliance 계정에서 제거되었습니다.',
        ],
        'passkey_added' => [
            'title' => '암호 키 추가됨',
            'body' => 'Kingshot Alliance 계정의 로그인 방법으로 새 암호가 등록되었습니다.',
        ],
        'passkey_renamed' => [
            'title' => '암호키 이름 바꾸기',
            'body' => 'Kingshot Alliance 계정에 대한 암호 레이블이 변경되었습니다.',
        ],
        'passkey_removed' => [
            'title' => '암호 키가 제거되었습니다',
            'body' => '암호 키 로그인 방법이 Kingshot Alliance 계정에서 제거되었습니다.',
        ],
        'mfa_enabled' => [
            'title' => '이중 인증 사용',
            'body' => '이제 Kingshot Alliance 계정에 대해 인증기 기반 이중 인증이 사용됩니다.',
        ],
        'mfa_disabled' => [
            'title' => '이중 인증 비활성화',
            'body' => 'Kingshot Alliance 계정에 대해 이중 인증이 비활성화되었습니다.',
        ],
        'recovery_codes_regenerated' => [
            'title' => '복구 코드 재생성됨',
            'body' => '이전 이중 인증 복구 코드가 교체되었으므로 더 이상 사용할 수 없습니다.',
        ],
        'deletion_requested' => [
            'title' => '계정 삭제 요청',
            'body' => '7일 삭제 냉각 기간이 Kingshot Alliance 계정에 대해 시작되었습니다.',
        ],
        'deletion_cancelled' => [
            'title' => '계정 삭제 취소됨',
            'body' => 'Kingshot Alliance 계정에 대한 보류 중인 삭제 요청이 취소되었습니다.',
        ],
    ],
];
