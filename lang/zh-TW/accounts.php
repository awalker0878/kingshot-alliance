<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => '驗證您的Kingshot Alliance電子郵件',
            'eyebrow' => '電子郵件驗證',
            'heading' => '驗證電子郵件',
            'intro' => '使用下面的安全連結驗證您的 Kingshot Alliance 帳戶的電子郵件地址。',
            'action' => '驗證電子郵件地址',
            'expiry' => '此驗證連結將在 :minutes 分鐘後到期。',
            'notice' => 'Kingshot Alliance 絕不會透過電子郵件驗證訊息詢問您的密碼。',
        ],
        'email_change_verify' => [
            'subject' => '驗證您的新Kingshot Alliance電子郵件地址',
            'eyebrow' => '安全核查',
            'heading' => '驗證您的新電子郵件地址',
            'intro' => '使用下面的安全連結來確認您的Kingshot Alliance帳戶的新電子郵件地址。 您當前的電子郵件在驗證成功之前保持有效。',
            'action' => '驗證新的電子郵件地址',
            'expiry' => '此驗證連結將在 :minutes 分鐘後到期。',
            'notice' => '如果您沒有請求此變更,請勿使用此連結並檢查您的帳戶安全性。',
        ],
        'email_changed' => [
            'subject' => '您的Kingshot Alliance電子郵件已更改',
            'eyebrow' => '安全通知',
            'heading' => '您的帳戶已被刪除',
            'intro' => '您的Kingshot Alliance帳戶電子郵件地址已變更為 :email。',
            'action' => '登入Kingshot Alliance',
            'notice' => '如果您沒有進行此更改,請確保您的帳戶安全並聯系網站管理員。',
        ],
        'reset' => [
            'subject' => '重設您的Kingshot Alliance密碼',
            'eyebrow' => '賬戶恢復',
            'heading' => '重設您的密碼',
            'intro' => '已為您的 Kingshot Alliance 帳戶請求密碼重置。 如果您提出了此請求,請使用下面的安全連結。',
            'action' => '重設密碼',
            'expiry' => '此密碼重設連結將在 :minutes 分鐘後到期。',
            'notice' => '如果您沒有請求重置密碼,您可以忽略此訊息。 您的密碼不會更改。',
        ],
        'fallback' => '如果按鈕無效,請將此連結複製並貼上到您的瀏覽器中:',
        'footer' => 'Kingshot Alliance 是獨立的第三方聯盟管理應用程式,適用於 Kingshot 玩家。',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => '請求更改電子郵件',
            'body' => '一個新的電子郵件地址正在等待驗證。 您當前的電子郵件地址將保持有效,直到新地址得到驗證。',
        ],
        'email_changed' => [
            'title' => '賬戶電子郵件更改',
            'body' => '您的 Kingshot Alliance 帳戶電子郵件在驗證新地址後已更改。',
        ],
        'password_changed' => [
            'title' => '密碼已更改',
            'body' => '您的Kingshot Alliance密碼已變更。其他工作階段與存取權杖已失效。',
        ],
        'password_added' => [
            'title' => '新增密碼登入',
            'body' => '已將 Kingshot Alliance 密碼新增為您帳戶的登入方法。',
        ],
        'password_removed' => [
            'title' => '密碼登入刪除',
            'body' => '您的 Kingshot Alliance 密碼登入方法已被刪除。 現有的密碼重置材料不再可用。',
        ],
        'google_connected' => [
            'title' => 'Google 登入連線',
            'body' => '已連線 Google 身份作為您的 Kingshot Alliance 帳戶的附加登入方法。',
        ],
        'google_disconnected' => [
            'title' => 'Google 登入斷開連線',
            'body' => 'Google 登入方法已從您的 Kingshot Alliance 帳戶中刪除。',
        ],
        'passkey_added' => [
            'title' => '新增了通用金鑰',
            'body' => '已為您的 Kingshot Alliance 帳戶註冊了新的通行碼作為登入方法。',
        ],
        'passkey_renamed' => [
            'title' => '重新命名通用金鑰',
            'body' => '您的 Kingshot Alliance 帳戶的通用金鑰標籤已更改。',
        ],
        'passkey_removed' => [
            'title' => '已刪除通用金鑰',
            'body' => '已從您的 Kingshot Alliance 帳戶中刪除了通鑰登入方法。',
        ],
        'mfa_enabled' => [
            'title' => '啟用雙重認證',
            'body' => '現在已為您的 Kingshot Alliance 帳戶啟用基於身份驗證器的雙重認證。',
        ],
        'mfa_disabled' => [
            'title' => '禁用雙重認證',
            'body' => '雙重認證已為您的 Kingshot Alliance 帳戶禁用。',
        ],
        'recovery_codes_regenerated' => [
            'title' => '恢復程式碼',
            'body' => '您以前的雙重認證恢復程式碼已被替換,無法再使用。',
        ],
        'deletion_requested' => [
            'title' => '請求刪除賬戶',
            'body' => '您的 Kingshot Alliance 帳戶已開始七天的刪除冷卻期。',
        ],
        'deletion_cancelled' => [
            'title' => '帳戶刪除取消',
            'body' => '您的 Kingshot Alliance 帳戶的待決刪除請求已被取消。',
        ],
    ],
];
