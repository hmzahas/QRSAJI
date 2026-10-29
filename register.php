<?php
// Mulai sesi untuk menyimpan status login dan token keamanan
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

// Variabel untuk menyimpan pesan error dan nilai input yang sudah diketik
$error = '';
$name = '';
$email = '';

// Proses form hanya kalau pengguna menekan tombol "Buat Akun"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan semua input dari form
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Validasi token CSRF — mencegah serangan dari situs lain
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';

        // Validasi nama: wajib diisi dan tidak boleh lebih dari 100 karakter
    } elseif ($name === '' || strlen($name) > 100) {
        $error = 'Nama wajib diisi dan maksimal 100 karakter.';

        // Validasi format email
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $error = 'Masukkan alamat email yang valid.';

        // Password minimal 8 karakter
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';

        // Konfirmasi password harus sama dengan password utama
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok.';

    } else {
        // Cek apakah email sudah terdaftar di database
        $check = $pdo->prepare(
            'SELECT id FROM users WHERE email = ? LIMIT 1'
        );
        $check->execute([$email]);

        if ($check->fetch()) {
            // Email sudah dipakai orang lain
            $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
        } else {
            // Hash password sebelum disimpan ke database (tidak disimpan teks asli)
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                // Simpan data pengguna baru ke tabel users
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$name, $email, $hash]);

                // Perbarui ID sesi untuk keamanan
                session_regenerate_id(true);

                // Simpan data pengguna ke sesi agar langsung login
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                // Arahkan ke dashboard setelah berhasil daftar
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                // Kalau error karena email duplikat (kode 23000 = constraint violation)
                if ($e->getCode() === '23000') {
                    $error = 'Email sudah terdaftar.';
                } else {
                    // Error lain — catat ke log dan tampilkan pesan umum
                    error_log($e->getMessage());
                    $error = 'Registrasi gagal. Silakan coba lagi.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - QRSAJI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page">

    <main class="auth-shell">
        <section class="auth-card">
            <!-- Logo QRSAJI yang bisa diklik untuk kembali ke halaman utama -->
            <a href="index.php" class="logo">
                <img src="assets/image/logo.png" alt="QRSAJI - Scan, Lihat, Pilih, Pesan">
            </a>
            <h1>Buat akunmu.</h1>
            <p class="auth-description">
                Daftar untuk mulai membangun menu digital usaha kamu.
            </p>

            <!-- Tampilkan pesan error kalau ada -->
            <?php if ($error !== ''): ?>
                <div class="form-alert" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <!-- Token tersembunyi untuk keamanan form -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <label for="name">Nama lengkap</label>
                <!-- Nilai diisi ulang kalau form gagal disubmit -->
                <input id="name" name="name" type="text" maxlength="100" autocomplete="name"
                    placeholder="Masukkan nama kamu" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                    required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" autocomplete="email"
                    placeholder="nama@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password"
                    placeholder="Minimal 8 karakter" required>

                <label for="confirm_password">Konfirmasi password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="8"
                    autocomplete="new-password" placeholder="Ulangi password" required>

                <button class="btn auth-submit" type="submit">
                    Buat Akun
                </button>
            </form>

            <!-- Link ke halaman login kalau sudah punya akun -->
            <p class="auth-switch">
                Sudah punya akun?
                <a href="login.php">Masuk di sini</a>
            </p>
        </section>

        <a href="index.php" class="back-home">Kembali ke halaman utama</a>
    </main>

</body>

</html>