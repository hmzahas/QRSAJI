<?php
// Mulai sesi untuk menyimpan status login pengguna
session_start();
// Hubungkan ke database
require_once __DIR__ . '/config/database.php';

// Kalau pengguna sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Buat token CSRF kalau belum ada — dipakai untuk keamanan form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Variabel untuk menyimpan pesan error dan nilai email yang sudah diketik
$error = '';
$email = '';

// Proses form hanya kalau pengguna menekan tombol "Masuk"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input dari form
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Cek apakah token CSRF cocok — mencegah serangan dari luar
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';

        // Validasi format email dan pastikan password tidak kosong
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 || $password === ''
    ) {
        $error = 'Email atau password tidak valid.';

    } else {
        // Cari pengguna di database berdasarkan email
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Kalau pengguna ditemukan dan password cocok
        if ($user && password_verify($password, $user['password'])) {
            // Perbarui ID sesi untuk keamanan (mencegah session fixation)
            session_regenerate_id(true);

            // Simpan data pengguna ke sesi
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Buat token CSRF baru setelah login berhasil
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Arahkan ke dashboard
            header('Location: dashboard.php');
            exit;
        }

        // Kalau email atau password salah
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - QRSAJI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page">

    <main class="auth-shell">
        <section class="auth-card">
            <!-- Logo QRSAJI yang bisa diklik untuk kembali ke halaman utama -->
            <a href="index.php" class="logo">
                <img src="assets/image/logo.png" alt="QRSAJI - Scan, Lihat, Pilih, Pesan">
            </a>

            <span class="mini-label">SELAMAT DATANG KEMBALI</span>
            <h1>Masuk akun.</h1>
            <p class="auth-description">
                Masuk untuk melanjutkan pengelolaan menu digitalmu.
            </p>

            <!-- Tampilkan pesan error kalau ada -->
            <?php if ($error !== ''): ?>
                <div class="form-alert" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <!-- Token tersembunyi untuk keamanan form -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <label for="email">Email</label>
                <!-- Input email — nilai diisi ulang kalau form gagal disubmit -->
                <input id="email" name="email" type="email" autocomplete="email" maxlength="150"
                    placeholder="nama@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password"
                    placeholder="Masukkan password" required>

                <button class="btn auth-submit" type="submit">
                    Masuk
                </button>
            </form>

            <!-- Link ke halaman daftar kalau belum punya akun -->
            <p class="auth-switch">
                Belum punya akun?
                <a href="register.php">Daftar sekarang</a>
            </p>
        </section>

        <a href="index.php" class="back-home"> Kembali ke halaman utama</a>
    </main>

</body>

</html>