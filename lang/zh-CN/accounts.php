<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => '验证您的Kingshot Alliance电子邮件',
            'eyebrow' => '电子邮件验证',
            'heading' => '验证电子邮箱',
            'intro' => '使用下面的安全链接验证您的 Kingshot Alliance 帐户的电子邮件地址。',
            'action' => '验证电子邮件地址',
            'expiry' => '此验证链接将在 :minutes 分钟后过期。',
            'notice' => 'Kingshot Alliance 绝不会通过电子邮件验证消息询问您的密码。',
        ],
        'email_change_verify' => [
            'subject' => '验证您的新Kingshot Alliance电子邮箱',
            'eyebrow' => '安全核查',
            'heading' => '验证您的新电子邮件地址',
            'intro' => '使用下面的安全链接来确认您的Kingshot Alliance帐户的新电子邮件地址。 您当前的电子邮件在验证成功之前保持有效。',
            'action' => '验证新的电子邮件地址',
            'expiry' => '此验证链接将在 :minutes 分钟后过期。',
            'notice' => '如果您没有请求此变更,请勿使用此链接并检查您的帐户安全性。',
        ],
        'email_changed' => [
            'subject' => '您的Kingshot Alliance电子邮件已更改',
            'eyebrow' => '安全通知',
            'heading' => '您的帐户已被删除',
            'intro' => '您的Kingshot Alliance账户电子邮箱已更改为 :email。',
            'action' => '登录Kingshot Alliance',
            'notice' => '如果您没有进行此更改,请确保您的帐户安全并联系网站管理员。',
        ],
        'reset' => [
            'subject' => '重设您的Kingshot Alliance密码',
            'eyebrow' => '账户恢复',
            'heading' => '重置您的密码',
            'intro' => '已为您的 Kingshot Alliance 帐户请求密码重置。 如果您提出了此请求,请使用下面的安全链接。',
            'action' => '重置密码',
            'expiry' => '此密码重设链接将在 :minutes 分钟后过期。',
            'notice' => '如果您没有请求重置密码,可以忽略此消息。 您的密码不会更改。',
        ],
        'fallback' => '如果按钮无效,请将此链接复制并粘贴到您的浏览器中:',
        'footer' => 'Kingshot Alliance 是一个独立的第三方联盟管理应用程序,适用于 Kingshot 玩家。',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => '请求更改电子邮件',
            'body' => '一个新的电子邮件地址正在等待验证。 您当前的电子邮件地址将保持有效,直到新地址得到验证。',
        ],
        'email_changed' => [
            'title' => '账户电子邮件更改',
            'body' => '您的 Kingshot Alliance 帐户电子邮件在验证新地址后已更改。',
        ],
        'password_changed' => [
            'title' => '密码已更改',
            'body' => '您的Kingshot Alliance密码已更改。其他会话和访问令牌已失效。',
        ],
        'password_added' => [
            'title' => '添加密码登录',
            'body' => '已将 Kingshot Alliance 密码添加为您帐户的登录方法。',
        ],
        'password_removed' => [
            'title' => '密码登录已删除',
            'body' => '您的 Kingshot Alliance 密码登录方法已被删除。 现有的密码重置材料不再可用。',
        ],
        'google_connected' => [
            'title' => 'Google 登录连接',
            'body' => '已连接 Google 身份作为您的 Kingshot Alliance 帐户的附加登录方法。',
        ],
        'google_disconnected' => [
            'title' => 'Google 登录断开连接',
            'body' => 'Google 登录方法已从您的 Kingshot Alliance 帐户中删除。',
        ],
        'passkey_added' => [
            'title' => '添加了通用密钥',
            'body' => '已为您的 Kingshot Alliance 帐户注册了新的通行码作为登录方法。',
        ],
        'passkey_renamed' => [
            'title' => '重命名通用密钥',
            'body' => '您的 Kingshot Alliance 帐户的通用密钥标签已更改。',
        ],
        'passkey_removed' => [
            'title' => '已删除通用密钥',
            'body' => '已从您的 Kingshot Alliance 帐户中删除了通钥登录方法。',
        ],
        'mfa_enabled' => [
            'title' => '启用双重认证',
            'body' => '现在已为您的 Kingshot Alliance 帐户启用基于身份验证器的双重认证。',
        ],
        'mfa_disabled' => [
            'title' => '禁用双重认证',
            'body' => '双重认证已为您的 Kingshot Alliance 帐户禁用。',
        ],
        'recovery_codes_regenerated' => [
            'title' => '恢复代码',
            'body' => '您以前的双重认证恢复代码已被替换,无法再使用。',
        ],
        'deletion_requested' => [
            'title' => '请求删除账户',
            'body' => '您的 Kingshot Alliance 帐户开始了七天的删除冷却期。',
        ],
        'deletion_cancelled' => [
            'title' => '帐户删除取消',
            'body' => '您的 Kingshot Alliance 帐户的待决删除请求已被取消。',
        ],
    ],
];
