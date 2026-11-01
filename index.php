<?php
$pageTitle = "QRSAJI - Menu Digital untuk UMKM";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <div class="nav-overlay" id="navOverlay"></div>

    <aside class="mobile-sidebar" id="mobileSidebar">
        <nav class="mobile-sidebar-nav">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#tentang">Tentang</a>
            <a href="login.php">Masuk</a>
            <a href="register.php" class="mobile-sidebar-cta">Mulai Gratis</a>
        </nav>
    </aside>

    <header class="navbar">
        <a href="index.php" class="logo">
            <img src="assets/image/logo.png" alt="QRSAJI - Scan, Lihat, Pilih, Pesan">
        </a>

        <nav>
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#tentang">Tentang</a>
        </nav>

        <div class="nav-actions">
            <a href="login.php" class="login-link">Masuk</a>
            <a href="register.php" class="btn btn-small">Mulai Gratis</a>
            <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">&#9776;</button>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <div class="eyebrow">
                    <span class="status-dot"></span>
                    SOLUSI DIGITAL UNTUK UMKM KULINER
                </div>

                <h1>
                    Menu usahamu,<br>
                    <span>lebih modern.</span>
                </h1>

                <p class="hero-description">
                    Buat menu digital yang menarik, kelola produk
                    dengan mudah, dan biarkan pelanggan melihat
                    menu cukup dengan memindai QR Code.
                </p>

                <div class="hero-actions">
                    <a href="register.php" class="btn">
                        Buat Menu Gratis
                    </a>
                    <a href="#cara-kerja" class="btn btn-outline">
                        Pelajari Cara Kerja
                    </a>
                </div>

                <div class="hero-note">
                    <span>✓</span> Mudah digunakan
                    <span>✓</span> Ramah untuk HP
                    <span>✓</span> Praktis untuk UMKM
                </div>
            </div>

            <div class="hero-visual">
                <div class="visual-label">VISUAL MENU DIGITAL</div>

                <div class="menu-card">
                    <div class="restaurant-cover">
                        <span class="restaurant-icon">QS</span>
                        <span class="open-label">
                            <span class="status-dot"></span> Buka
                        </span>
                    </div>

                    <div class="restaurant-info">
                        <span class="mini-label">MENU DIGITAL</span>
                        <h2>Ruang Rasa</h2>
                        <p>Tempat rasa bertemu cerita.</p>
                    </div>

                    <div class="category-list">
                        <span class="category active">Semua</span>
                        <span class="category">Makanan</span>
                        <span class="category">Minuman</span>
                    </div>

                    <div class="product-item">
                        <div class="food-placeholder food-one">🍜</div>
                        <div class="product-info">
                            <h3>Nasi Ayam Spesial</h3>
                            <p>Ayam berbumbu dengan nasi hangat</p>
                            <strong>Rp25.000</strong>
                        </div>
                    </div>

                    <div class="product-item">
                        <div class="food-placeholder food-two">🥤</div>
                        <div class="product-info">
                            <h3>Es Kopi Susu</h3>
                            <p>Kopi susu dengan rasa seimbang</p>
                            <strong>Rp18.000</strong>
                        </div>
                    </div>

                    <div class="menu-footer">
                        <span>Powered by</span>
                        <strong>QRSAJI.</strong>
                    </div>
                </div>

                <div class="floating-card">
                    <span class="floating-icon">⌗</span>
                    <div>
                        <strong>Menu digital siap!</strong>
                        <p>Satu QR untuk semua menu</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="trust-strip" id="tentang">
            <p>DIRANCANG UNTUK BERBAGAI JENIS USAHA</p>
            <div class="business-types">
                <span>Warung Makan</span>
                <span>Kedai Kopi</span>
                <span>Minuman Kekinian</span>
                <span>Kafe & Restoran</span>
                <span>Kantin</span>
            </div>
        </section>

        <section class="features section" id="fitur">
            <div class="section-heading">
                <span class="mini-label">KENAPA QRSAJI?</span>
                <h2>Kelola menu tanpa<br>harus ribet.</h2>
                <p>Semua kebutuhan menu digital dalam satu tempat.</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-icon">▤</span>
                    <h3>Menu Digital</h3>
                    <p>Tampilkan nama produk, foto, harga, dan deskripsi dengan rapi.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon">⌗</span>
                    <h3>QR Code Praktis</h3>
                    <p>Bagikan menu melalui QR Code yang dapat dipindai pelanggan.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon">✳</span>
                    <h3>Kelola Produk</h3>
                    <p>Perbarui harga, foto, dan ketersediaan produk kapan saja.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon">↗</span>
                    <h3>Ramah untuk HP</h3>
                    <p>Menu dirancang agar nyaman dilihat dari berbagai ukuran layar.</p>
                </article>
            </div>
        </section>

        <section class="how-section section" id="cara-kerja">
            <div class="section-heading">
                <span class="mini-label">CARA KERJA</span>
                <h2>Dari menu ke QR,<br>hanya beberapa langkah.</h2>
            </div>

            <div class="steps">
                <article class="step">
                    <span>01</span>
                    <h3>Buat akun</h3>
                    <p>Daftar dan isi informasi usaha milikmu.</p>
                </article>

                <article class="step">
                    <span>02</span>
                    <h3>Tambahkan menu</h3>
                    <p>Masukkan foto, nama, harga, dan kategori produk.</p>
                </article>

                <article class="step">
                    <span>03</span>
                    <h3>Bagikan QR Code</h3>
                    <p>Publikasikan menu dan bagikan kepada pelanggan.</p>
                </article>
            </div>
        </section>

        <section class="cta-section">
            <span class="mini-label">SAATNYA BERTRANSFORMASI</span>
            <h2>Buat menu usahamu<br>lebih mudah diakses.</h2>
            <p>Mulai bangun pengalaman menu digital bersama QRSAJI.</p>
            <a href="register.php" class="btn btn-light">
                Mulai Buat Menu
            </a>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php">
                    <img src="assets/image/logo.png" alt="QRSAJI - Scan, Lihat, Pilih, Pesan">
                </a>
                <!-- <p>"Menu digital yang lebih praktis untuk usaha kuliner Indonesia."</p> -->
            </div>

            <div class="footer-col">
                <span class="footer-heading">NAVIGASI</span>
                <a href="#fitur">Fitur</a>
                <a href="#cara-kerja">Cara Kerja</a>
                <a href="#tentang">Tentang</a>
            </div>

            <div class="footer-col">
                <span class="footer-heading">AKUN</span>
                <a href="login.php">Masuk</a>
                <a href="register.php">Daftar</a>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© <?= date('Y') ?> QRSAJI. Semua hak dilindungi.</span>
        </div>
    </footer>

<script>
(function(){

  // 1. Navbar scroll effect
  const navbar = document.querySelector('.navbar');
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });

  // Hamburger sidebar
  const overlay = document.getElementById('navOverlay');
  const sidebar = document.getElementById('mobileSidebar');
  function openSidebar() { sidebar.classList.add('open'); overlay.classList.add('show'); }
  function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('show'); }
  document.getElementById('mobileToggle')?.addEventListener('click', (e) => { e.stopPropagation(); openSidebar(); });
  document.addEventListener('click', () => { if (sidebar.classList.contains('open')) closeSidebar(); });

  // 2. Scroll reveal dengan stagger per group
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('revealed');
        revealObserver.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll(
    '.section-heading, .trust-strip, .cta-section'
  ).forEach(el => { el.classList.add('reveal'); revealObserver.observe(el); });

  // Stagger feature cards
  document.querySelectorAll('.feature-card').forEach((el, i) => {
    el.classList.add('reveal');
    el.style.transitionDelay = (i * 0.1) + 's';
    revealObserver.observe(el);
  });

  // Stagger steps
  document.querySelectorAll('.step').forEach((el, i) => {
    el.classList.add('reveal');
    el.style.transitionDelay = (i * 0.13) + 's';
    revealObserver.observe(el);
  });

  // Stagger business-types spans
  document.querySelectorAll('.business-types span').forEach((el, i) => {
    el.classList.add('reveal');
    el.style.transitionDelay = (i * 0.08) + 's';
    revealObserver.observe(el);
  });

  // 3. Floating card animasi
  const floating = document.querySelector('.floating-card');
  if (floating) floating.style.animation = 'floatUp 3s ease-in-out infinite';

  // 4. Menu card mock — auto-cycle kategori
  const cats = document.querySelectorAll('.category');
  const items = document.querySelectorAll('.product-item');
  let ci = 0;
  if (cats.length) {
    setInterval(() => {
      cats.forEach(c => c.classList.remove('active'));
      ci = (ci + 1) % cats.length;
      cats[ci].classList.add('active');
      // fade items
      items.forEach(it => { it.style.opacity = '0'; it.style.transform = 'translateY(6px)'; });
      setTimeout(() => {
        items.forEach(it => { it.style.opacity = '1'; it.style.transform = 'translateY(0)'; });
      }, 150);
    }, 2200);
  }

  // 5. Parallax subtle pada hero visual
  window.addEventListener('scroll', () => {
    const hv = document.querySelector('.hero-visual');
    if (hv) hv.style.transform = 'translateY(' + (window.scrollY * 0.06) + 'px)';
  }, { passive: true });

  // 6. Magnetic hover pada tombol .btn
  document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('mousemove', e => {
      const r = btn.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width - 0.5) * 8;
      const y = ((e.clientY - r.top) / r.height - 0.5) * 8;
      btn.style.transform = `translate(${x}px, ${y}px) translateY(-2px)`;
    });
    btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
  });

})();
</script>
</body>

</html>