<?php
// Mulai sesi agar bisa mengakses dan menghapus data sesi yang ada
session_start();

// Hanya izinkan logout lewat metode POST (bukan langsung buka URL di browser)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Ambil token keamanan dari form dan dari sesi
$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

// Validasi token CSRF — mencegah logout paksa dari situs lain
if (
    !is_string($token) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    http_response_code(403);
    exit('Permintaan tidak valid. Silakan kembali ke dashboard.');
}

// Kosongkan semua data sesi (hapus info login)
$_SESSION = [];

// Hapus cookie sesi di browser pengguna agar benar-benar keluar
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,       // Waktu kedaluwarsa di masa lalu agar cookie langsung dihapus
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan sesi sepenuhnya di server
session_destroy();

// Arahkan pengguna kembali ke halaman login
header('Location: login.php');
exit;
