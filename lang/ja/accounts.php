<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'あなたのKingshot Allianceメールを確認してください',
            'eyebrow' => 'メールの確認',
            'heading' => 'メールアドレスを確認',
            'intro' => '下の安全なリンクを使って、あなたの Kingshot Alliance アカウントのメールアドレスを確認してください。',
            'action' => 'メールアドレスを確認',
            'expiry' => 'この検証リンクは :minutes分後に失効します',
            'notice' => 'Kingshot Allianceは決してメールでパスワードを 尋ねません',
        ],
        'email_change_verify' => [
            'subject' => 'Kingshot Allianceの新しいメールアドレスを確認',
            'eyebrow' => 'セキュリティ検証',
            'heading' => '新しいメールアドレスを確認',
            'intro' => '下の安全なリンクを使って、あなたの Kingshot Alliance アカウントの新しいメールアドレスを確認してください。 あなたの現在のメールアドレスは、確認が成功するまで有効になります。',
            'action' => '新しいメールアドレスを確認',
            'expiry' => 'この検証リンクは :minutes分後に失効します',
            'notice' => 'この変更を要求しなかった場合は、このリンクを使用しないで、アカウントのセキュリティを確認してください。',
        ],
        'email_changed' => [
            'subject' => 'あなたのKingshot Allianceのメールアドレスが変わりました',
            'eyebrow' => 'セキュリティ通知',
            'heading' => 'アカウントのメールアドレスが変更されました',
            'intro' => 'Kingshot Allianceアカウントのメールアドレスが:emailに変更されました。',
            'action' => 'Kingshot Allianceにログイン',
            'notice' => 'この変更を行っていない場合は、アカウントを安全にしてサイト管理者に連絡してください。',
        ],
        'reset' => [
            'subject' => 'Kingshot Allianceのパスワードを再設定',
            'eyebrow' => 'アカウントの復旧',
            'heading' => 'パスワードをリセット',
            'intro' => 'あなたの Kingshot Alliance アカウントのパスワードのリセットが要求されました。 この要求を行った場合は、下のセキュリティリンクを使用してください。',
            'action' => 'パスワードをリセット',
            'expiry' => 'このパスワードリセットリンクは :minutes 分後に失効します。',
            'notice' => 'パスワードのリセットを要求しなかった場合は、このメッセージを無視してください。 あなたのパスワードは変わりません。',
        ],
        'fallback' => 'ボタンが動かない場合は、次のリンクをコピーしてブラウザに貼り付けてください:',
        'footer' => 'Kingshot Allianceは、Kingshotプレイヤー向けの独立したサードパーティの同盟管理アプリケーションである。',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'メールアドレスの変更を要求',
            'body' => '新しいメールアドレスが確認を待っています。 新しいアドレスが確認されるまで、現在のメールアドレスは有効になります。',
        ],
        'email_changed' => [
            'title' => 'アカウントのメールアドレスが変更されました',
            'body' => 'あなたのKingshot Allianceアカウントのメールアドレスは、新しいアドレスの確認後に変更されました。',
        ],
        'password_changed' => [
            'title' => 'パスワードが変更されました',
            'body' => 'あなたのKingshot Allianceパスワードが変更されました。 他のセッションとアクセストークンは無効になった。',
        ],
        'password_added' => [
            'title' => 'パスワードログインを追加',
            'body' => 'あなたのアカウントのログイン方法として Kingshot Alliance のパスワードが追加されました。',
        ],
        'password_removed' => [
            'title' => 'パスワードログインを削除しました',
            'body' => 'あなたの Kingshot Alliance パスワードのログイン方法が削除されました。 既存のパスワードリセット資料はもはや使用できません。',
        ],
        'google_connected' => [
            'title' => 'Google サインイン接続',
            'body' => 'Google のアイデンティティが Kingshot Alliance アカウントの追加のログイン方法として接続されています。',
        ],
        'google_disconnected' => [
            'title' => 'Google サインインが切断されました',
            'body' => 'Google サインイン方法があなたのKingshot Allianceアカウントから削除されました。',
        ],
        'passkey_added' => [
            'title' => 'パスキーを追加',
            'body' => 'あなたの Kingshot Alliance アカウントのログイン方法として新しいパスキーが登録されました。',
        ],
        'passkey_renamed' => [
            'title' => 'パスキーの名前を変更',
            'body' => 'あなたの Kingshot Alliance アカウントのパスキーラベルが変更されました。',
        ],
        'passkey_removed' => [
            'title' => 'パスキーを削除しました',
            'body' => 'パスキーログインメソッドがあなたの Kingshot Alliance アカウントから削除されました。',
        ],
        'mfa_enabled' => [
            'title' => '二重認証を有効に',
            'body' => '認証者ベースの二要素認証が、あなたの Kingshot Alliance アカウントに対して有効になりました。',
        ],
        'mfa_disabled' => [
            'title' => '二重認証を無効にしました',
            'body' => 'あなたの Kingshot Alliance アカウントに対して二要素認証が無効になっています。',
        ],
        'recovery_codes_regenerated' => [
            'title' => '復旧コードが再生されました',
            'body' => 'あなたの以前の二要素復元コードは置き換えられ、もはや使用できません。',
        ],
        'deletion_requested' => [
            'title' => 'アカウントの削除を要求',
            'body' => 'あなたのKingshot Allianceアカウントに 7日間の削除休止期間が始まりました',
        ],
        'deletion_cancelled' => [
            'title' => 'アカウントの削除をキャンセル',
            'body' => 'あなたの Kingshot Alliance のアカウントの削除要求がキャンセルされました。',
        ],
    ],
];
