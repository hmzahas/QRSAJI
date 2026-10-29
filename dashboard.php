<?php
// Mulai sesi untuk mengakses data login pengguna
session_start();
// Hubungkan ke database
require_once __DIR__ . '/config/database.php';

// Kalau belum login, tolak akses
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        // Kalau request AJAX, kembalikan JSON error 401
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk kembali.']);
        exit;
    }
    // Kalau bukan AJAX, arahkan ke halaman login
    header('Location: login.php');
    exit;
}

// Ambil ID pengguna yang sedang login
$userId = (int) $_SESSION['user_id'];

// Buat token CSRF kalau belum ada — dipakai untuk keamanan semua form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Fungsi untuk mengamankan teks sebelum ditampilkan di HTML (mencegah XSS)
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk mengirim respons JSON dan langsung menghentikan eksekusi
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Fungsi untuk membuat slug URL dari nama usaha
// Contoh: "Ruang Rasa" menjadi "ruang-rasa-a1b2c3d4" (kode acak di belakang agar unik)
function slugifyBusiness(string $name): string
{
    $base = strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base); // Ganti karakter selain huruf/angka dengan tanda hubung
    $base = trim($base, '-');
    if ($base === '')
        $base = 'usaha';
    return $base . '-' . bin2hex(random_bytes(4));
}

// Fungsi untuk mengambil data usaha milik pengguna — mengembalikan null kalau tidak ditemukan
function ownedBusiness(PDO $pdo, int $businessId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM busines WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$businessId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Tangani semua request AJAX (dari JavaScript dashboard)
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Validasi token CSRF sebelum memproses apapun
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(['ok' => false, 'message' => 'Sesi formulir tidak valid. Muat ulang halaman.'], 403);
    }

    // Ambil nama aksi yang dikirim dari form (contoh: business_create, product_delete, dll)
    $action = (string) ($_POST['action'] ?? '');
    try {
        // Proses aksi yang berhubungan dengan profil usaha
        if (str_starts_with($action, 'business_')) {
            // Ambil dan bersihkan semua input data usaha
            $name = trim((string) ($_POST['business_name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
            $hours = trim((string) ($_POST['opening_hours'] ?? ''));

            // Validasi input hanya untuk aksi tambah dan edit
            if (in_array($action, ['business_create', 'business_update'], true)) {
                if ($name === '' || mb_strlen($name) > 150)
                    jsonResponse(['ok' => false, 'message' => 'Nama usaha wajib diisi (maksimal 150 karakter).'], 422);
                if (mb_strlen($whatsapp) > 30 || mb_strlen($hours) > 150)
                    jsonResponse(['ok' => false, 'message' => 'Nomor WhatsApp atau jam operasional terlalu panjang.'], 422);
            }

            // Proses upload logo restoran kalau ada file yang dikirim
            $logoPath = null;
            $logoUpload = $_FILES['business_logo'] ?? null;
            if ($logoUpload && $logoUpload['error'] !== UPLOAD_ERR_NO_FILE) {
                // Cek error upload dan batas ukuran 2 MB
                if ($logoUpload['error'] !== UPLOAD_ERR_OK || $logoUpload['size'] > 2 * 1024 * 1024)
                    jsonResponse(['ok' => false, 'message' => 'Foto restoran gagal diunggah atau melebihi 2 MB.'], 422);
                // Deteksi tipe file berdasarkan isi file (lebih aman dari ekstensi)
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($logoUpload['tmp_name']);
                $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($exts[$mime]))
                    jsonResponse(['ok' => false, 'message' => 'Foto harus berformat JPG, PNG, atau WEBP.'], 422);
                // Buat folder tujuan kalau belum ada
                $uploadDir = __DIR__ . '/assets/uploads/business';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                // Buat nama file acak agar tidak bentrok
                $filename = bin2hex(random_bytes(16)) . '.' . $exts[$mime];
                if (!move_uploaded_file($logoUpload['tmp_name'], $uploadDir . '/' . $filename))
                    jsonResponse(['ok' => false, 'message' => 'Foto gagal disimpan.'], 500);
                $logoPath = 'assets/uploads/business/' . $filename;
            }

            // Tambah usaha baru ke database
            if ($action === 'business_create') {
                $stmt = $pdo->prepare('INSERT INTO busines (user_id,business_name,slug,description,logo,address,whatsapp,opening_hours) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$userId, $name, slugifyBusiness($name), $description ?: null, $logoPath, $address ?: null, $whatsapp ?: null, $hours ?: null]);
                jsonResponse(['ok' => true, 'message' => 'Profil usaha berhasil ditambahkan.']);
            }

            // Perbarui data usaha yang sudah ada
            if ($action === 'business_update') {
                $id = filter_var($_POST['business_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$id || !ownedBusiness($pdo, (int) $id, $userId))
                    jsonResponse(['ok' => false, 'message' => 'Usaha tidak ditemukan.'], 404);
                $existing = ownedBusiness($pdo, (int) $id, $userId);
                // Kalau tidak ada logo baru, pakai logo lama
                if ($logoPath === null)
                    $logoPath = $existing['logo'];
                // Proses upload banner
                $bannerPath = null;
                $bannerUpload = $_FILES['business_banner'] ?? null;
                if ($bannerUpload && $bannerUpload['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($bannerUpload['error'] !== UPLOAD_ERR_OK || $bannerUpload['size'] > 4 * 1024 * 1024)
                        jsonResponse(['ok' => false, 'message' => 'Banner gagal diunggah atau melebihi 4 MB.'], 422);
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($bannerUpload['tmp_name']);
                    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($exts[$mime]))
                        jsonResponse(['ok' => false, 'message' => 'Banner harus berformat JPG, PNG, atau WEBP.'], 422);
                    $uploadDir = __DIR__ . '/assets/uploads/business';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0755, true);
                    $filename = bin2hex(random_bytes(16)) . '.' . $exts[$mime];
                    if (!move_uploaded_file($bannerUpload['tmp_name'], $uploadDir . '/' . $filename))
                        jsonResponse(['ok' => false, 'message' => 'Banner gagal disimpan.'], 500);
                    $bannerPath = 'assets/uploads/business/' . $filename;
                } else {
                    $bannerPath = $existing['banner'] ?? null;
                }
                $stmt = $pdo->prepare('UPDATE busines SET business_name=?,description=?,logo=?,banner=?,address=?,whatsapp=?,opening_hours=? WHERE id=? AND user_id=?');
                $stmt->execute([$name, $description ?: null, $logoPath, $bannerPath, $address ?: null, $whatsapp ?: null, $hours ?: null, $id, $userId]);
                // Hapus logo lama dari server kalau sudah diganti
                if ($logoUpload && $logoUpload['error'] === UPLOAD_ERR_OK && !empty($existing['logo']) && $existing['logo'] !== $logoPath) {
                    $old = __DIR__ . '/' . ltrim($existing['logo'], '/');
                    if (is_file($old))
                        @unlink($old);
                }
                jsonResponse(['ok' => true, 'message' => 'Profil usaha berhasil diperbarui.']);
            }

            // Hapus usaha dari database
            if ($action === 'business_delete') {
                $id = filter_var($_POST['business_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$id || !ownedBusiness($pdo, (int) $id, $userId))
                    jsonResponse(['ok' => false, 'message' => 'Usaha tidak ditemukan.'], 404);
                $stmt = $pdo->prepare('DELETE FROM busines WHERE id=? AND user_id=?');
                $stmt->execute([$id, $userId]);
                jsonResponse(['ok' => true, 'message' => 'Profil usaha berhasil dihapus.']);
            }
        }

        $businessId = filter_var($_POST['business_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$businessId || !ownedBusiness($pdo, (int) $businessId, $userId)) {
            jsonResponse(['ok' => false, 'message' => 'Pilih usaha yang valid terlebih dahulu.'], 422);
        }
        $businessId = (int) $businessId;

        if (in_array($action, ['category_create', 'category_update', 'category_delete'], true)) {
            if ($action === 'category_create') {
                $name = trim((string) ($_POST['category_name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100)
                    jsonResponse(['ok' => false, 'message' => 'Nama kategori wajib diisi (maksimal 100 karakter).'], 422);
                $stmt = $pdo->prepare('INSERT INTO menu (business_id,category_name) VALUES (?,?)');
                $stmt->execute([$businessId, $name]);
                jsonResponse(['ok' => true, 'message' => 'Kategori berhasil ditambahkan.']);
            }
            $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
            $check = $pdo->prepare('SELECT id FROM menu WHERE id=? AND business_id=?');
            $check->execute([$categoryId, $businessId]);
            if (!$categoryId || !$check->fetch())
                jsonResponse(['ok' => false, 'message' => 'Kategori tidak ditemukan.'], 404);
            if ($action === 'category_update') {
                $name = trim((string) ($_POST['category_name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100)
                    jsonResponse(['ok' => false, 'message' => 'Nama kategori wajib diisi (maksimal 100 karakter).'], 422);
                $stmt = $pdo->prepare('UPDATE menu SET category_name=? WHERE id=? AND business_id=?');
                $stmt->execute([$name, $categoryId, $businessId]);
                jsonResponse(['ok' => true, 'message' => 'Kategori berhasil diperbarui.']);
            }
            if ($action === 'category_delete') {
                $stmt = $pdo->prepare('UPDATE product SET menu_id=NULL WHERE menu_id=? AND business_id=?');
                $stmt->execute([$categoryId, $businessId]);
                $stmt = $pdo->prepare('DELETE FROM menu WHERE id=? AND business_id=?');
                $stmt->execute([$categoryId, $businessId]);
                jsonResponse(['ok' => true, 'message' => 'Kategori berhasil dihapus. Produk terkait tetap tersimpan tanpa kategori.']);
            }
        }

        if (in_array($action, ['product_create', 'product_update', 'product_delete'], true)) {
            if ($action === 'product_delete') {
                $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
                $stmt = $pdo->prepare('SELECT image FROM product WHERE id=? AND business_id=?');
                $stmt->execute([$id, $businessId]);
                $product = $stmt->fetch();
                if (!$id || !$product)
                    jsonResponse(['ok' => false, 'message' => 'Produk tidak ditemukan.'], 404);
                $stmt = $pdo->prepare('DELETE FROM product WHERE id=? AND business_id=?');
                $stmt->execute([$id, $businessId]);
                if (!empty($product['image'])) {
                    $path = __DIR__ . '/' . ltrim($product['image'], '/');
                    if (is_file($path) && str_starts_with(realpath($path) ?: '', realpath(__DIR__ . '/assets/uploads/products') ?: '/nonexistent'))
                        @unlink($path);
                }
                jsonResponse(['ok' => true, 'message' => 'Produk berhasil dihapus.']);
            }

            $name = trim((string) ($_POST['product_name'] ?? ''));
            $description = trim((string) ($_POST['product_description'] ?? ''));
            $priceRaw = str_replace(',', '.', trim((string) ($_POST['price'] ?? '')));
            $price = filter_var($priceRaw, FILTER_VALIDATE_FLOAT);
            $categoryId = trim((string) ($_POST['menu_id'] ?? '')) === '' ? null : filter_var($_POST['menu_id'], FILTER_VALIDATE_INT);
            $available = isset($_POST['is_available']) ? 1 : 0;
            if ($name === '' || mb_strlen($name) > 150)
                jsonResponse(['ok' => false, 'message' => 'Nama produk wajib diisi (maksimal 150 karakter).'], 422);
            if ($price === false || $price < 0 || $price > 9999999999)
                jsonResponse(['ok' => false, 'message' => 'Harga harus berupa angka nol atau lebih.'], 422);
            if ($categoryId !== null) {
                if (!$categoryId)
                    jsonResponse(['ok' => false, 'message' => 'Kategori tidak valid.'], 422);
                $stmt = $pdo->prepare('SELECT id FROM menu WHERE id=? AND business_id=?');
                $stmt->execute([$categoryId, $businessId]);
                if (!$stmt->fetch())
                    jsonResponse(['ok' => false, 'message' => 'Kategori tidak ditemukan pada usaha ini.'], 422);
            }

            $imagePath = null;
            $upload = $_FILES['product_image'] ?? null;
            if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 4 * 1024 * 1024)
                    jsonResponse(['ok' => false, 'message' => 'Foto gagal diunggah atau melebihi 4 MB.'], 422);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($upload['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime]))
                    jsonResponse(['ok' => false, 'message' => 'Foto harus berformat JPG, PNG, atau WEBP.'], 422);
                $uploadDir = __DIR__ . '/assets/uploads/products';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($upload['tmp_name'], $uploadDir . '/' . $filename))
                    jsonResponse(['ok' => false, 'message' => 'Foto gagal disimpan.'], 500);
                $imagePath = 'assets/uploads/products/' . $filename;
            }

            if ($action === 'product_create') {
                $stmt = $pdo->prepare('INSERT INTO product (business_id,menu_id,product_name,description,price,image,is_available) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$businessId, $categoryId, $name, $description ?: null, $price, $imagePath, $available]);
                jsonResponse(['ok' => true, 'message' => 'Produk berhasil ditambahkan.']);
            }
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('SELECT image FROM product WHERE id=? AND business_id=?');
            $stmt->execute([$id, $businessId]);
            $old = $stmt->fetch();
            if (!$id || !$old)
                jsonResponse(['ok' => false, 'message' => 'Produk tidak ditemukan.'], 404);
            if ($imagePath === null)
                $imagePath = $old['image'];
            $stmt = $pdo->prepare('UPDATE product SET menu_id=?,product_name=?,description=?,price=?,image=?,is_available=? WHERE id=? AND business_id=?');
            $stmt->execute([$categoryId, $name, $description ?: null, $price, $imagePath, $available, $id, $businessId]);
            if ($upload && $upload['error'] === UPLOAD_ERR_OK && !empty($old['image']) && $old['image'] !== $imagePath) {
                $oldPath = __DIR__ . '/' . ltrim($old['image'], '/');
                if (is_file($oldPath))
                    @unlink($oldPath);
            }
            jsonResponse(['ok' => true, 'message' => 'Produk berhasil diperbarui.']);
        }

        jsonResponse(['ok' => false, 'message' => 'Aksi tidak dikenal.'], 400);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Proses database gagal. Periksa log PHP jika masalah berlanjut.'], 500);
    } catch (Throwable $ex) {
        error_log($ex->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Terjadi kesalahan saat memproses permintaan.'], 500);
    }
}

$stmt = $pdo->prepare('SELECT * FROM busines WHERE user_id=? ORDER BY id DESC');
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();
$businessCount = count($businesses);
$businessId = filter_var($_GET['business_id'] ?? ($businesses[0]['id'] ?? ''), FILTER_VALIDATE_INT);
if (!$businessId || !ownedBusiness($pdo, (int) $businessId, $userId))
    $businessId = $businesses ? (int) $businesses[0]['id'] : 0;
$currentBusiness = $businessId ? ownedBusiness($pdo, (int) $businessId, $userId) : null;
$productCount = 0;
$categoryCount = 0;
$availableCount = 0;
$products = [];
$categories = [];
if ($currentBusiness) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product WHERE business_id=?');
    $stmt->execute([$businessId]);
    $productCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product WHERE business_id=? AND is_available=1');
    $stmt->execute([$businessId]);
    $availableCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT * FROM menu WHERE business_id=? ORDER BY sort_order,id');
    $stmt->execute([$businessId]);
    $categories = $stmt->fetchAll();
    $categoryCount = count($categories);
    $stmt = $pdo->prepare('SELECT p.*,m.category_name FROM product p LEFT JOIN menu m ON m.id=p.menu_id AND m.business_id=p.business_id WHERE p.business_id=? ORDER BY p.id DESC');
    $stmt->execute([$businessId]);
    $products = $stmt->fetchAll();
}
$displayName = trim((string) ($_SESSION['user_name'] ?? 'Pemilik Usaha'));
$initial = strtoupper(mb_substr($displayName ?: 'P', 0, 1));
$publicUrl = $currentBusiness ? ('menu.php?slug=' . rawurlencode($currentBusiness['slug'])) : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard QRSAJI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body class="qrs-dashboard">
    <aside class="sidebar" id="sidebar">
        <a class="side-brand" href="dashboard.php"><img src="assets/image/logo.png" alt="QRSAJI"></a>
        <div class="side-caption">NAVIGASI</div>
        <nav class="side-nav" aria-label="Navigasi dashboard">
            <button class="nav-item active" data-section="overview"><span>▦</span>Dashboard</button>
            <button class="nav-item" data-section="business"><span>⌂</span>Profil Usaha</button>
            <button class="nav-item" data-section="products"><span>♧</span>Item Menu</button>
            <button class="nav-item" data-section="categories"><span>▤</span>Kategori</button>
            <button class="nav-item" data-section="qr"><span>▦</span>Kode QR</button>
            <button class="nav-item" data-section="settings"><span>⚙</span>Pengaturan</button>
        </nav>
        <div class="sidebar-bottom">
            <div class="side-caption">AKUN</div>
            <form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <button class="nav-item logout-item" type="submit"><span>↪</span>Keluar</button>
            </form>
        </div>
    </aside>

    <div class="dashboard-main">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Buka navigasi">☰</button>
            <div class="topbar-title">Dashboard Pemilik</div>
            <div class="topbar-user"><span class="avatar"><?= h($initial) ?></span>
                <div><strong><?= h($displayName) ?></strong><small>Pemilik Usaha</small></div>
            </div>
        </header>

        <main class="main-content">
            <div class="notice" id="notice" role="status" hidden></div>
            <?php if (!$businesses): ?>
                <section class="view-section active" id="section-overview">
                    <div class="welcome-panel">
                        <div><span class="eyebrow">DASHBOARD PEMILIK</span>
                            <h1>Halo, <?= h($displayName) ?>!</h1>
                            <p>Kelola informasi usaha dan menu digital kamu dari satu tempat.</p>
                        </div>
                        <div class="welcome-art" aria-hidden="true">🏪</div>
                    </div>
                    <div class="empty-business-prompt">
                        <div class="empty-illustration">⌂</div>
                        <h2>Mulai dengan profil usahamu</h2>
                        <p>Tambahkan informasi usaha terlebih dahulu agar kamu bisa mengelola kategori, produk, dan QR menu.
                        </p><button class="btn" data-go="business">+ Tambah Profil Usaha</button>
                    </div>
                </section>
            <?php else: ?>
                <section class="view-section active" id="section-overview">
                    <div class="welcome-panel">
                        <div><span class="eyebrow">DASHBOARD PEMILIK</span>
                            <h1>Halo, <?= h($displayName) ?>!</h1>
                            <p>Kelola semua informasi usaha dan menu digital dalam satu tempat.</p>
                        </div>
                    </div>
                    <div class="business-switcher">
                        <label for="businessSwitcher">Usaha yang dikelola</label>
                        <select id="businessSwitcher" class="control">
                            <?php foreach ($businesses as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $businessId ? 'selected' : '' ?>>
                                    <?= h($b['business_name']) ?>
                                </option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline" data-go="business">Kelola Profil</button>
                    </div>
                    <div class="section-heading-row">
                        <div><span class="eyebrow">RINGKASAN</span>
                            <h2>Ringkasan usaha</h2>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <article class="stat-card"><span class="stat-icon">⌂</span>
                            <div><strong><?= $businessCount ?></strong><small>Usaha Terdaftar</small></div>
                        </article>
                        <article class="stat-card"><span class="stat-icon">♧</span>
                            <div><strong><?= $productCount ?></strong><small>Total Menu</small></div>
                        </article>
                        <article class="stat-card"><span class="stat-icon">▤</span>
                            <div><strong><?= $categoryCount ?></strong><small>Kategori</small></div>
                        </article>
                        <article class="stat-card"><span class="stat-icon">▦</span>
                            <div><strong><?= $currentBusiness ? 'Aktif' : '—' ?></strong><small>Status QR Menu</small></div>
                        </article>
                    </div>
                    <div class="content-card quick-card">
                        <div><span class="eyebrow">AKSES CEPAT</span>
                            <h2>Kelola menu digitalmu</h2>
                            <p>Perbarui produk atau kategori tanpa meninggalkan dashboard ini.</p>
                        </div>
                        <div class="quick-actions"><button class="btn" data-go="products">Kelola Item Menu</button><button
                                class="btn btn-outline" data-go="categories">Kelola Kategori</button></div>
                    </div>
                    <div class="content-card recent-card">
                        <div class="card-heading">
                            <div>
                                <h2>Menu terbaru</h2>
                                <p>Produk yang terakhir ditambahkan.</p>
                            </div><button class="text-action" data-go="products">Lihat semua →</button>
                        </div>
                        <?php if (!$products): ?>
                            <div class="empty-state"><span>🍽️</span><strong>Belum ada menu</strong>
                                <p>Tambahkan produk pertamamu dari bagian Item Menu.</p><button class="btn" data-go="products">+
                                    Tambah Menu</button>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Kategori</th>
                                            <th>Harga</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach (array_slice($products, 0, 5) as $p): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-cell"><?php if ($p['image']): ?><img
                                                                src="<?= h($p['image']) ?>" alt=""><?php else: ?><span
                                                                class="product-thumb">🍽️</span><?php endif; ?><strong><?= h($p['product_name']) ?></strong>
                                                    </div>
                                                </td>
                                                <td><?= h($p['category_name'] ?: 'Tanpa kategori') ?></td>
                                                <td>Rp<?= number_format((float) $p['price'], 0, ',', '.') ?></td>
                                                <td><span
                                                        class="status <?= $p['is_available'] ? 'status-active' : 'status-off' ?>"><?= $p['is_available'] ? 'Aktif' : 'Habis' ?></span>
                                                </td>
                                            </tr><?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="view-section" id="section-business">
                <div class="page-heading">
                    <div><span class="eyebrow">PENGELOLAAN USAHA</span>
                        <h1>Profil Usaha</h1>
                        <p>Kelola informasi usaha yang terhubung dengan akunmu.</p>
                    </div><button class="btn" id="addBusinessBtn">+ Tambah Usaha Baru</button>
                </div>
                <div class="business-cards">
                    <?php if (!$businesses): ?>
                        <div class="empty-state"><span>🏪</span><strong>Belum ada usaha</strong>
                            <p>Tambahkan profil usaha pertamamu.</p>
                        </div>
                    <?php else:
                        foreach ($businesses as $b): ?>
                            <article class="business-card">
                                <div class="business-logo">
                                    <?php if (!empty($b['logo'])): ?>
                                        <img src="<?= h($b['logo']) ?>" alt="<?= h($b['business_name']) ?>">
                                    <?php else: ?>
                                        🏪
                                    <?php endif; ?>
                                </div>
                                <div class="business-details">
                                    <div class="business-title">
                                        <h2><?= h($b['business_name']) ?></h2><span class="status status-active">Aktif</span>
                                    </div>
                                    <p><?= h($b['description'] ?: 'Belum ada deskripsi usaha.') ?></p>
                                    <?php if ($b['address']): ?><small>⌖
                                            <?= h($b['address']) ?></small><?php endif; ?><?php if ($b['whatsapp']): ?><small>☏
                                            <?= h($b['whatsapp']) ?></small><?php endif; ?><?php if ($b['opening_hours']): ?><small>◷
                                            <?= h($b['opening_hours']) ?></small><?php endif; ?><small class="slug-line">URL menu:
                                        <a href="menu.php?slug=<?= rawurlencode($b['slug']) ?>" target="_blank"
                                            rel="noopener">menu.php?slug=<?= h($b['slug']) ?></a></small>
                                </div>
                                <div class="row-actions"><button class="btn btn-outline btn-sm"
                                        data-edit-business="<?= (int) $b['id'] ?>" data-logo="<?= h($b['logo'] ?? '') ?>"
                                        data-banner="<?= h($b['banner'] ?? '') ?>">Edit</button><button
                                        class="btn btn-danger btn-sm" data-delete-business="<?= (int) $b['id'] ?>"
                                        data-name="<?= h($b['business_name']) ?>">Hapus</button></div>
                            </article><?php endforeach; endif; ?>
                </div>
            </section>

            <section class="view-section" id="section-products">
                <div class="page-heading">
                    <div><span class="eyebrow">KATALOG PRODUK</span>
                        <h1>Item Menu</h1>
                        <p>Kelola makanan dan minuman yang tampil di menu digitalmu.</p>
                    </div><button class="btn" id="addProductBtn" <?= !$currentBusiness ? 'disabled' : '' ?>>+ Tambah
                        Menu</button>
                </div>
                <div class="toolbar">
                    <div class="filter-pills"><button class="filter-pill active" data-filter="all">Semua
                            (<?= count($products) ?>)</button><button class="filter-pill"
                            data-filter="available">Aktif</button><button class="filter-pill"
                            data-filter="unavailable">Habis</button></div><input class="control search-input"
                        id="productSearch" placeholder="Cari menu...">
                </div>
                <div class="content-card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                <?php foreach ($products as $p): ?>
                                    <tr data-product-row data-id="<?= (int) $p['id'] ?>"
                                        data-available="<?= (int) $p['is_available'] ?>"
                                        data-search="<?= h(strtolower($p['product_name'] . ' ' . $p['category_name'])) ?>">
                                        <td>
                                            <div class="product-cell"><?php if ($p['image']): ?><img
                                                        src="<?= h($p['image']) ?>" alt=""><?php else: ?><span
                                                        class="product-thumb">🍽️</span><?php endif; ?>
                                                <div>
                                                    <strong><?= h($p['product_name']) ?></strong><small><?= h($p['description'] ?: '') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= h($p['category_name'] ?: 'Tanpa kategori') ?></td>
                                        <td>Rp<?= number_format((float) $p['price'], 0, ',', '.') ?></td>
                                        <td><span
                                                class="status <?= $p['is_available'] ? 'status-active' : 'status-off' ?>"><?= $p['is_available'] ? 'Aktif' : 'Habis' ?></span>
                                        </td>
                                        <td>
                                            <div class="row-actions"><button class="btn btn-outline btn-sm"
                                                    data-edit-product="<?= (int) $p['id'] ?>"
                                                    data-product="<?= h(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">Edit</button><button
                                                    class="btn btn-danger btn-sm"
                                                    data-delete-product="<?= (int) $p['id'] ?>"
                                                    data-name="<?= h($p['product_name']) ?>">Hapus</button></div>
                                        </td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!$products): ?>
                        <div class="empty-state" id="emptyProducts"><span>🍽️</span><strong>Belum ada item menu</strong>
                            <p>Mulai tambahkan produk makanan atau minuman.</p>
                        </div><?php endif; ?>
                </div>
                <form action="upload_menu.php" method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                    <input type="hidden" name="business_id" value="<?= (int) $businessId ?>">

                    <label for="product_id">Pilih produk</label>

                    <select name="product_id" id="product_id" required>
                        <option value="">-- Pilih produk --</option>

                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>">
                                <?= h($product['product_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="image">Foto makanan atau minuman</label>

                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>

                    <button type="submit" class="btn-primary">
                        Upload Gambar
                    </button>
                </form>
            </section>

            <section class="view-section" id="section-categories">
                <div class="page-heading">
                    <div><span class="eyebrow">PENGELOLAAN MENU</span>
                        <h1>Kategori</h1>
                        <p>Kelompokkan produk agar pelanggan lebih mudah menemukan menu.</p>
                    </div><button class="btn" id="addCategoryBtn" <?= !$currentBusiness ? 'disabled' : '' ?>>+ Tambah
                        Kategori</button>
                </div>
                <div class="content-card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Kategori</th>
                                    <th>Jumlah Menu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c):
                                    $count = 0;
                                    foreach ($products as $p)
                                        if ((int) $p['menu_id'] === (int) $c['id'])
                                            $count++; ?>
                                    <tr>
                                        <td><strong><?= h($c['category_name']) ?></strong></td>
                                        <td><?= $count ?> produk</td>
                                        <td>
                                            <div class="row-actions"><button class="btn btn-outline btn-sm"
                                                    data-edit-category="<?= (int) $c['id'] ?>"
                                                    data-name="<?= h($c['category_name']) ?>">Edit</button><button
                                                    class="btn btn-danger btn-sm"
                                                    data-delete-category="<?= (int) $c['id'] ?>"
                                                    data-name="<?= h($c['category_name']) ?>">Hapus</button></div>
                                        </td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php if (!$categories): ?>
                        <div class="empty-state"><span>▤</span><strong>Belum ada kategori</strong>
                            <p>Buat kategori seperti Makanan, Minuman, atau Camilan.</p>
                        </div><?php endif; ?>
                </div>
            </section>

            <section class="view-section" id="section-qr">
                <div class="page-heading">
                    <div><span class="eyebrow">BAGIKAN MENU</span>
                        <h1>Kode QR</h1>
                        <p>Buat kode QR yang mengarah ke halaman menu publik usahamu.</p>
                    </div>
                </div>
                <?php if (count($businesses) > 1): ?>
                    <div class="business-switcher">
                        <label for="qrBusinessSwitcher">Pilih usaha</label>
                        <select id="qrBusinessSwitcher" class="control">
                            <?php foreach ($businesses as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $businessId ? 'selected' : '' ?>>
                                    <?= h($b['business_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($currentBusiness): ?>
                    <div class="qr-layout content-card">
                        <div class="qr-image-wrap"><img id="qrImage"
                                src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/' . $publicUrl) ?>"
                                alt="QR Code menu <?= h($currentBusiness['business_name']) ?>"><strong><?= h($currentBusiness['business_name']) ?></strong><small><?= h($publicUrl) ?></small><a
                                class="btn" id="openPublicMenu" href="<?= h($publicUrl) ?>" target="_blank"
                                rel="noopener">Buka Menu Publik</a></div>
                        <div class="qr-details"><span class="eyebrow">INFORMASI QR</span>
                            <h2>QR Menu <?= h($currentBusiness['business_name']) ?></h2>
                            <p>Pelanggan dapat memindai kode ini untuk melihat produk yang kamu publikasikan.</p>
                            <label>Alamat menu</label>
                            <div class="copy-url"><input class="control" id="publicUrl" readonly
                                    value="<?= h((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/' . $publicUrl) ?>"><button
                                    class="btn btn-outline" id="copyUrlBtn">Salin</button></div><a class="btn"
                                id="downloadQrBtn"
                                href="https://api.qrserver.com/v1/create-qr-code/?size=800x800&format=png&data=<?= rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/' . $publicUrl) ?>"
                                download="qrsaji-qr.png" target="_blank" rel="noopener">Unduh QR Code</a>
                            <p class="helper-text">Pembuatan gambar QR menggunakan layanan QR eksternal dan memerlukan
                                koneksi internet.</p>
                        </div>
                    </div><?php else: ?>
                    <div class="empty-state content-card"><span>▦</span><strong>Buat profil usaha dahulu</strong>
                        <p>Kode QR akan tersedia setelah kamu menambahkan usaha.</p><button class="btn"
                            data-go="business">Tambah Profil Usaha</button>
                    </div><?php endif; ?>
            </section>

            <section class="view-section" id="section-settings">
                <div class="page-heading">
                    <div><span class="eyebrow">AKUN</span>
                        <h1>Pengaturan</h1>
                        <p>Informasi akun yang digunakan untuk masuk ke QRSAJI.</p>
                    </div>
                </div>
                <div class="content-card settings-card">
                    <div class="settings-row"><span class="settings-icon">◉</span>
                        <div><small>Nama Pemilik</small><strong><?= h($displayName) ?></strong></div>
                    </div>
                    <div class="settings-row"><span class="settings-icon">✉</span>
                        <div><small>Email</small><strong><?= h($_SESSION['user_email'] ?? '') ?></strong></div>
                    </div>
                    <div class="settings-row"><span class="settings-icon">⌂</span>
                        <div><small>Jumlah Usaha</small><strong><?= $businessCount ?> usaha terdaftar</strong></div>
                    </div>
                    <form method="POST" action="logout.php" class="settings-logout"><input type="hidden"
                            name="csrf_token" value="<?= h($csrf) ?>"><button class="btn btn-danger"
                            type="submit">Keluar dari Akun</button></form>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-backdrop" id="modalBackdrop" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-heading">
                <div><span class="eyebrow">QRSAJI</span>
                    <h2 id="modalTitle">Formulir</h2>
                </div><button class="modal-close" id="modalClose" aria-label="Tutup">×</button>
            </div>
            <div id="modalMessage" class="form-alert" hidden></div>
            <form id="modalForm" enctype="multipart/form-data">
                <div id="modalFields" class="modal-fields"></div>
                <div class="modal-actions"><button type="button" class="btn btn-outline"
                        id="modalCancel">Batal</button><button type="submit" class="btn"
                        id="modalSubmit">Simpan</button></div>
            </form>
        </div>
    </div>
    <script>window.QRSAJI = <?= json_encode(['csrf' => $csrf, 'businessId' => $businessId, 'hasBusiness' => (bool) $currentBusiness, 'categories' => array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['category_name']], $categories)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
    <script src="assets/js/dashboard.js" defer></script>
</body>

</html>