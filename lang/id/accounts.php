<?php

declare(strict_types=1);

return [
    'mail' => [
        'verify' => [
            'subject' => 'Verifikasi email Kingshot Alliance Anda',
            'eyebrow' => 'VERIFIKASI SULTAN',
            'heading' => 'Verifikasi email Anda',
            'intro' => 'Gunakan tautan aman di bawah ini untuk memverifikasi alamat surel untuk akun Kingshot Alliance Anda.',
            'action' => 'Verifikasi alamat surel',
            'expiry' => 'Sambungan verifikasi ini akan habis dalam :minutes menit.',
            'notice' => 'Kingshot Alliance tidak akan pernah meminta sandi Anda melalui pesan verifikasi email.',
        ],
        'email_change_verify' => [
            'subject' => 'Verifikasi email Kingshot Alliance baru Anda',
            'eyebrow' => 'VERIFIKASI KEamanan',
            'heading' => 'Verifikasi alamat surel baru Anda',
            'intro' => 'Gunakan tautan aman di bawah ini untuk mengkonfirmasi alamat surel baru ini untuk akun Kingshot Alliance Anda. Surel Anda saat ini tetap aktif sampai verifikasi berhasil.',
            'action' => 'Verifikasi alamat surel baru',
            'expiry' => 'Sambungan verifikasi ini akan habis dalam :minutes menit.',
            'notice' => 'Jika Anda tidak meminta perubahan ini, jangan gunakan tautan ini dan periksa keamanan akun Anda.',
        ],
        'email_changed' => [
            'subject' => 'Email Kingshot Alliancemu berubah',
            'eyebrow' => 'PERINGATAN KEamanan',
            'heading' => 'Alamat email akun Anda telah berubah',
            'intro' => 'Alamat surel untuk akun Kingshot Alliance Anda telah diubah menjadi :email.',
            'action' => 'Daftar masuk ke Kingshot Alliance',
            'notice' => 'Jika Anda tidak melakukan perubahan ini, amankan akun Anda dan hubungi administrator situs.',
        ],
        'reset' => [
            'subject' => 'Reset sandi Kingshot Alliance Anda',
            'eyebrow' => 'PENGUMUMAN AKUNTANSI',
            'heading' => 'Setel ulang kata sandi Anda',
            'intro' => 'Sebuah reset sandi telah diminta untuk akun Kingshot Alliance Anda. Gunakan tautan aman di bawah ini jika Anda membuat permintaan ini.',
            'action' => 'Atur ulang kata sandi',
            'expiry' => 'Sambungan reset kata sandi ini akan habis dalam :minutes menit.',
            'notice' => 'Jika Anda tidak meminta reset sandi, Anda dapat mengabaikan pesan ini. Kata sandimu tidak akan berubah.',
        ],
        'fallback' => 'Jika tombol ini tidak bekerja, salin dan tempelkan tautan ini ke peramban Anda:',
        'footer' => 'Kingshot Alliance adalah aplikasi manajemen aliansi pihak ketiga independen untuk pemain Kingshot.',
    ],
    'security' => [
        'email_change_requested' => [
            'title' => 'Perubahan surel diminta',
            'body' => 'Alamat surel baru menunggu verifikasi. Surel Anda saat ini tetap aktif sampai alamat baru diverifikasi.',
        ],
        'email_changed' => [
            'title' => 'Email akun berubah',
            'body' => 'Alamat email akun Kingshot Alliance Anda telah berubah setelah verifikasi alamat baru.',
        ],
        'password_changed' => [
            'title' => 'Sandi diubah',
            'body' => 'Kata sandi Kingshot Alliance Anda telah berubah. Sesi lain dan token akses dinyatakan tidak sah.',
        ],
        'password_added' => [
            'title' => 'Tambahkan kata sandi login',
            'body' => 'Sandi Kingshot Alliance telah ditambahkan sebagai metode masuk untuk akun Anda.',
        ],
        'password_removed' => [
            'title' => 'Log masuk sandi dihapus',
            'body' => 'Metode masuk sandi Kingshot Alliance Anda telah dihapus. Material reset sandi yang ada tidak lagi dapat digunakan.',
        ],
        'google_connected' => [
            'title' => 'Google login terhubung',
            'body' => 'Identitas Google telah terhubung sebagai metode masuk tambahan untuk akun Kingshot Alliance Anda.',
        ],
        'google_disconnected' => [
            'title' => 'Google login terputus',
            'body' => 'Metode masuk Google telah dihapus dari akun Kingshot Alliance Anda.',
        ],
        'passkey_added' => [
            'title' => 'Kunci sandi ditambahkan',
            'body' => 'Sebuah kunci sandi baru telah terdaftar sebagai metode masuk untuk akun Kingshot Alliance Anda.',
        ],
        'passkey_renamed' => [
            'title' => 'Kunci sandi dinamai ulang',
            'body' => 'Sebuah label kunci sandi telah diubah untuk akun Kingshot Alliance Anda.',
        ],
        'passkey_removed' => [
            'title' => 'Kunci sandi dihapus',
            'body' => 'Metode masuk kunci sandi telah dihapus dari akun Kingshot Alliance Anda.',
        ],
        'mfa_enabled' => [
            'title' => 'Otentikasi dua-faktor diaktifkan',
            'body' => 'Otentikasi dua faktor berbasis otentikasi sekarang diaktifkan untuk akun Kingshot Alliance Anda.',
        ],
        'mfa_disabled' => [
            'title' => 'Otentikasi dua-faktor dimatikan',
            'body' => 'Otentikasi dua-faktor telah dimatikan untuk akun Kingshot Alliance Anda.',
        ],
        'recovery_codes_regenerated' => [
            'title' => 'Kode pemulihan dihasilkan kembali',
            'body' => 'Kode pemulihan dua faktor sebelumnya telah diganti dan tidak dapat digunakan lagi.',
        ],
        'deletion_requested' => [
            'title' => 'Penghapusan akun diminta',
            'body' => 'Periode penghapusan selama tujuh hari telah dimulai untuk akun Kingshot Alliancemu.',
        ],
        'deletion_cancelled' => [
            'title' => 'Penghapusan akun dibatalkan',
            'body' => 'Permintaan penghapusan yang menunggu untuk akun Kingshot Alliance Anda telah dibatalkan.',
        ],
    ],
];
