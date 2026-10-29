<?php
// Mulai sesi agar bisa cek apakah pengguna sudah login
session_start();

// Kalau belum login, arahkan ke halaman login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kalau sudah login, langsung arahkan ke bagian "Profil Usaha" di dashboard
header('Location: dashboard.php#section-business');
exit;
