<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Kingshot Alliance e-posta adresini doğrula',
            'eyebrow' => 'E-MAIL TESTİ',
            'heading' => 'E-postanızı doğrulayın',
            'intro' => 'Kingshot Alliance hesabınız için e-posta adresinizi doğrulamak için aşağıdaki güvenli bağlantıyı kullanın.',
            'action' => 'E-posta adresini doğrula',
            'expiry' => 'Bu doğrulama bağlantısı :minutes dakika içinde sona erer.',
            'notice' => 'Kingshot Alliance e-posta doğrulama mesajı yoluyla asla şifrenizi sormayacaktır.',
        ],
        'email_change_verify' => [
            'subject' => 'Yeni Kingshot Alliance e-posta adresinizi doğrulayın',
            'eyebrow' => 'GÜVENLİK TESLİMATI',
            'heading' => 'Yeni e-posta adresinizi doğrulayın',
            'intro' => 'Kingshot Alliance hesabınız için bu yeni e-posta adresini doğrulamak için aşağıdaki güvenli bağlantıyı kullanın. Mevcut e-posta adresiniz doğrulama başarılı olana kadar aktif kalır.',
            'action' => 'Yeni e-posta adresini doğrula',
            'expiry' => 'Bu doğrulama bağlantısı :minutes dakika içinde sona erer.',
            'notice' => 'Eğer bu değişikliği istemediyseniz, bu bağlantıyı kullanmayın ve hesabınızın güvenliğini gözden geçirin.',
        ],
        'email_changed' => [
            'subject' => 'Kingshot Alliance e-posta adresiniz değişti.',
            'eyebrow' => 'GÜVENLİK İTİBARI',
            'heading' => 'Hesabınızın e-posta adresi değiştirildi',
            'intro' => 'Kingshot Alliance numaralı hesabınız için e-posta adresi :email olarak değiştirildi.',
            'action' => 'Kingshot Alliance\'e giriş yap',
            'notice' => 'Eğer bu değişikliği yapmadınızsa, hesabınızı güvence altına alın ve site yöneticisiyle iletişime geçin.',
        ],
        'reset' => [
            'subject' => 'Kingshot Alliance şifrenizi geri yükleyin',
            'eyebrow' => 'Hesap Geri Alımı',
            'heading' => 'Parolanızı sıfırlayın',
            'intro' => 'Kingshot Alliance numaralı hesabınız için şifre geri yüklenmesi istendi. Eğer bu talebi siz yaptıysanız, aşağıdaki güvenli bağlantıyı kullanın.',
            'action' => 'Şifreyi sıfırla',
            'expiry' => 'Bu şifre yeniden oluşturma bağlantısı :minutes dakika içinde sona erer.',
            'notice' => 'Eğer şifreyi yeniden koymayı istemediyseniz, bu mesajı görmezden gelebilirsiniz. Şifreniz değişmez.',
        ],
        'fallback' => 'Eğer düğme çalışmazsa, bu bağlantıyı kopyalayıp tarayıcınıza yapıştırın:',
        'footer' => 'Kingshot Alliance, Kingshot oyuncuları için bağımsız üçüncü taraf ittifak yönetimi uygulamasıdır.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'E-posta değişikliği istendi',
            'body' => 'Yeni bir e-posta adresi doğrulanmayı bekliyor. Mevcut e-posta adresiniz yeni adres doğrulanana kadar aktif olarak kalır.',
        ],
        'email_changed' => [
            'title' => 'Hesap e-posta adresi değişti',
            'body' => 'Kingshot Alliance hesabınızın e-posta adresi yeni adresin doğrulanması sonrasında değiştirildi.',
        ],
        'password_changed' => [
            'title' => 'Şifre değiştirildi',
            'body' => 'Kingshot Alliance şifreniz değiştirildi. Diğer oturumlar ve erişim tokenleri geçersiz sayıldı.',
        ],
        'password_added' => [
            'title' => 'Şifre girişi eklendi',
            'body' => 'Hesabınız için bir giriş yöntemi olarak Kingshot Alliance numaralı bir şifre eklendi.',
        ],
        'password_removed' => [
            'title' => 'Şifre girişi kaldırıldı',
            'body' => 'Kingshot Alliance şifrenizle giriş yönteminiz kaldırıldı. Mevcut şifre yeniden oluşturma malzemesi artık kullanılamaz.',
        ],
        'google_connected' => [
            'title' => 'Google giriş bağlandı',
            'body' => 'Kingshot Alliance numaralı hesabınız için ek bir giriş yöntemi olarak Google numaralı bir kimlik bağlandı.',
        ],
        'google_disconnected' => [
            'title' => 'Google giriş bağlantısı kesildi',
            'body' => 'Google giriş yöntemi Kingshot Alliance hesabınızdan kaldırıldı.',
        ],
        'passkey_added' => [
            'title' => 'Parola anahtarı eklendi',
            'body' => 'Kingshot Alliance hesabınız için yeni bir şifre anahtarı bir giriş yöntemi olarak kaydedildi.',
        ],
        'passkey_renamed' => [
            'title' => 'Parola anahtarı yeniden adlandırıldı',
            'body' => 'Kingshot Alliance numaralı hesabınız için bir şifre etiketi değiştirildi.',
        ],
        'passkey_removed' => [
            'title' => 'Parola anahtarı kaldırıldı',
            'body' => 'Bir şifre giriş yöntemi Kingshot Alliance hesabınızdan kaldırıldı.',
        ],
        'mfa_enabled' => [
            'title' => 'İki faktörlü kimlik doğrulama etkinleştirildi',
            'body' => 'Kimlik doğrulayıcısına dayalı iki faktörlü kimlik doğrulaması artık Kingshot Alliance hesabınız için etkinleştirildi.',
        ],
        'mfa_disabled' => [
            'title' => 'İki faktörlü kimlik doğrulama etkisizleştirildi',
            'body' => 'İki faktörlü kimlik doğrulaması Kingshot Alliance hesabınız için engellendi.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Kurtarma kodları yeniden üretildi',
            'body' => 'Önceki iki faktörlü kurtarma kodlarınız değiştirildi ve artık kullanılamaz.',
        ],
        'deletion_requested' => [
            'title' => 'Hesap silme isteği',
            'body' => 'Kingshot Alliance numaralı hesabınız için yedi günlük bir silinme süreci başladı.',
        ],
        'deletion_cancelled' => [
            'title' => 'Hesap silme iptal edildi',
            'body' => 'Kingshot Alliance numaralı hesabınız için bekleyen silme isteği iptal edildi.',
        ],
    ],
];
