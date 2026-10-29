<?php
// Mulai sesi untuk cek apakah pengguna sudah login
session_start();

// Hubungkan ke database
require_once __DIR__ . '/config/database.php';

// Kalau belum login, tolak akses
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Silakan login terlebih dahulu.');
}

// Hanya izinkan metode POST (bukan akses langsung lewat URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

// Validasi token CSRF — mencegah upload dari situs lain
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'] ?? '',
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Token keamanan tidak valid.');
}

// Ambil ID pengguna, produk, dan usaha dari form
$userId = (int) $_SESSION['user_id'];
$productId = (int) ($_POST['product_id'] ?? 0);
$businessId = (int) ($_POST['business_id'] ?? 0);

// Pastikan semua data wajib tersedia
if (
    $productId < 1 ||
    $businessId < 1 ||
    !isset($_FILES['image'])
) {
    http_response_code(400);
    exit('Data upload tidak lengkap.');
}

// Pastikan usaha benar-benar milik pengguna yang sedang login
$stmt = $pdo->prepare(
    'SELECT id FROM busines WHERE id = ? AND user_id = ?'
);
$stmt->execute([$businessId, $userId]);

if (!$stmt->fetch()) {
    http_response_code(403);
    exit('Usaha tidak ditemukan atau bukan milik kamu.');
}

// Pastikan produk yang dipilih memang ada di usaha tersebut
// Sekaligus ambil path gambar lama untuk dihapus nanti
$stmt = $pdo->prepare(
    'SELECT id, image FROM product
     WHERE id = ? AND business_id = ?'
);
$stmt->execute([$productId, $businessId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

// Ambil data file yang diunggah
$file = $_FILES['image'];

// Cek apakah upload berhasil tanpa error
if ($file['error'] !== UPLOAD_ERR_OK) {
    exit('Upload gagal. Silakan pilih gambar lagi.');
}

// Batasi ukuran file maksimal 2 MB
$maxSize = 2 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    exit('Ukuran gambar maksimal 2 MB.');
}

// Deteksi tipe file berdasarkan isi file (bukan ekstensi) — lebih aman
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

// Hanya izinkan format gambar JPG, PNG, dan WEBP
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (!isset($allowedTypes[$mime])) {
    exit('Format gambar harus JPG, PNG, atau WEBP.');
}

// Verifikasi bahwa file benar-benar bisa dibaca sebagai gambar
if (@getimagesize($file['tmp_name']) === false) {
    exit('File yang diunggah bukan gambar yang valid.');
}

// Tentukan folder tujuan upload
$uploadDir = __DIR__ . '/assets/uploads/products/';

// Buat folder kalau belum ada
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        exit('Folder upload tidak dapat dibuat.');
    }
}

// Buat nama file acak agar tidak bentrok dengan file lain
$fileName = bin2hex(random_bytes(16))
    . '.' . $allowedTypes[$mime];

$destination = $uploadDir . $fileName;

// Pindahkan file dari folder sementara ke folder upload
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    exit('Gambar gagal disimpan.');
}

// Path relatif yang akan disimpan ke database
$imagePath = 'assets/uploads/products/' . $fileName;

try {
    // Perbarui kolom image di tabel product
    $stmt = $pdo->prepare(
        'UPDATE product
        SET image = ?
        WHERE id = ? AND business_id = ?'
    );

    $stmt->execute([
        $imagePath,
        $productId,
        $businessId
    ]);
} catch (Throwable $e) {
    // Kalau gagal simpan ke database, hapus file yang sudah terlanjur diupload
    if (is_file($destination)) {
        unlink($destination);
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Gagal menyimpan gambar ke database.');
}

// Hapus gambar lama setelah gambar baru berhasil disimpan ke database
$oldImage = $product['image'] ?? '';

if (
    $oldImage !== '' &&
    str_starts_with($oldImage, 'assets/uploads/products/')
) {
    $oldFile = __DIR__ . '/' . $oldImage;

    // Hapus file lama kalau masih ada
    if (is_file($oldFile)) {
        unlink($oldFile);
    }
}

// Kembali ke dashboard dengan notifikasi berhasil
header(
    'Location: dashboard.php?business_id='
    . $businessId
    . '&section=products&upload=success'
);
exit;
