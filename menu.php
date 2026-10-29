<?php
require_once __DIR__ . '/config/database.php';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') { http_response_code(404); exit('Menu tidak ditemukan.'); }
$stmt = $pdo->prepare('SELECT * FROM busines WHERE slug=? LIMIT 1');
$stmt->execute([$slug]);
$business = $stmt->fetch();
if (!$business) { http_response_code(404); exit('Menu tidak ditemukan.'); }
$stmt = $pdo->prepare('SELECT p.*,m.category_name FROM product p LEFT JOIN menu m ON m.id=p.menu_id AND m.business_id=p.business_id WHERE p.business_id=? ORDER BY m.sort_order,m.id,p.id');
$stmt->execute([$business['id']]);
$products = $stmt->fetchAll();
$grouped = [];
foreach ($products as $p) {
    $grouped[$p['category_name'] ?: 'Lainnya'][] = $p;
}
$categories = array_keys($grouped);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($business['business_name']) ?> — Menu Digital</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f5f5f0;color:#1a1a1a;min-height:100vh}

/* NAVBAR */
.mn-nav{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid #e8e8e0;padding:0 clamp(16px,4vw,48px)}
.mn-nav-inner{display:flex;align-items:center;gap:16px;height:68px;max-width:1200px;margin:0 auto}
.mn-logo{display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0}
.mn-logo img{width:44px;height:44px;object-fit:cover;border-radius:50%}
.mn-logo-name{font-weight:800;font-size:15px;color:#1a3028}
.mn-address{display:flex;align-items:center;gap:5px;font-size:12px;color:#666;margin-left:4px}
.mn-spacer{flex:1}

/* CATEGORY TABS */
.mn-tabs{background:#fff;border-bottom:1px solid #e8e8e0;padding:0 clamp(16px,4vw,48px);overflow-x:auto;scrollbar-width:none}
.mn-tabs::-webkit-scrollbar{display:none}
.mn-tabs-inner{display:flex;gap:0;max-width:1200px;margin:0 auto}
.mn-tab{padding:14px 18px;font-size:13px;font-weight:600;color:#666;border:none;background:none;cursor:pointer;white-space:nowrap;border-bottom:2px solid transparent;transition:color .15s,border-color .15s}
.mn-tab:hover{color:#1a3028}
.mn-tab.active{color:#1a3028;border-bottom-color:#1a3028}

/* BANNER */
.mn-banner{max-width:1200px;margin:24px auto;padding:0 clamp(16px,4vw,48px)}
.mn-banner-img{width:100%;height:clamp(160px,28vw,320px);object-fit:cover;border-radius:16px;display:block}
.mn-banner-placeholder{width:100%;height:clamp(160px,28vw,320px);border-radius:16px;background:linear-gradient(120deg,#1a3028,#2f5b48);display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:36px 40px;color:#fff}
.mn-banner-placeholder h2{font-size:clamp(22px,4vw,38px);font-weight:800;line-height:1.2;max-width:340px}
.mn-banner-placeholder p{font-size:13px;color:#c8ddd0;margin-top:10px;max-width:320px}

/* CONTENT */
.mn-content{max-width:1200px;margin:0 auto;padding:0 clamp(16px,4vw,48px) 60px}

/* CATEGORY SECTION */
.mn-section{margin-bottom:40px}
.mn-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.mn-section-title{font-size:20px;font-weight:800;color:#1a1a1a}

/* PRODUCT GRID — horizontal scroll */
.mn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}

/* PRODUCT CARD */
.mn-card{background:#fff;border-radius:14px;overflow:hidden;border:1px solid #ebebeb;display:flex;flex-direction:column;transition:box-shadow .18s}
.mn-card:hover{box-shadow:0 6px 24px #0001}
.mn-card-img{width:100%;aspect-ratio:1;object-fit:cover;background:#f0f0ea;display:block}
.mn-card-img-placeholder{width:100%;aspect-ratio:1;background:#f0f0ea;display:grid;place-items:center;font-size:36px}
.mn-card-body{padding:12px;flex:1;display:flex;flex-direction:column;gap:6px}
.mn-card-name{font-size:13px;font-weight:700;color:#1a1a1a;line-height:1.3}
.mn-card-desc{font-size:11px;color:#888;line-height:1.4;flex:1}
.mn-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:8px}
.mn-card-price{font-size:14px;font-weight:800;color:#1a3028}
.mn-card-sold{font-size:10px;color:#a64b43;background:#f9e8e5;border-radius:5px;padding:3px 7px;font-weight:600}

/* EMPTY */
.mn-empty{text-align:center;padding:60px 20px;color:#888}
.mn-empty span{font-size:40px;display:block;margin-bottom:12px}

/* FOOTER */
.mn-footer{text-align:center;padding:24px;font-size:11px;color:#aaa;border-top:1px solid #e8e8e0}
.mn-footer strong{color:#1a3028}

@media(max-width:600px){
  .mn-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .mn-address{display:none}
}
@media(max-width:380px){
  .mn-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<nav class="mn-nav">
  <div class="mn-nav-inner">
    <a class="mn-logo" href="#">
      <?php if (!empty($business['logo'])): ?>
        <img src="<?= h($business['logo']) ?>" alt="<?= h($business['business_name']) ?>">
      <?php endif; ?>
      <span class="mn-logo-name"><?= h($business['business_name']) ?></span>
    </a>
    <?php if ($business['address']): ?>
    <div class="mn-address">
      <span>📍</span><span><?= h($business['address']) ?></span>
    </div>
    <?php endif; ?>
    <div class="mn-spacer"></div>
    <?php if ($business['whatsapp']): ?>
      <a href="https://wa.me/<?= h(preg_replace('/\D/','',$business['whatsapp'])) ?>" target="_blank" rel="noopener"
         style="font-size:12px;font-weight:700;color:#1a3028;text-decoration:none;border:1px solid #1a3028;border-radius:8px;padding:8px 14px">
        WhatsApp
      </a>
    <?php endif; ?>
  </div>
</nav>

<?php if ($categories): ?>
<div class="mn-tabs" id="mnTabs">
  <div class="mn-tabs-inner">
    <?php foreach ($categories as $i => $cat): ?>
      <button class="mn-tab <?= $i===0?'active':'' ?>" data-cat="<?= h($cat) ?>"><?= h($cat) ?></button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mn-banner">
  <?php if (!empty($business['banner'])): ?>
    <img class="mn-banner-img" src="<?= h($business['banner']) ?>" alt="Banner <?= h($business['business_name']) ?>">
  <?php else: ?>
    <div class="mn-banner-placeholder">
      <h2><?= h($business['business_name']) ?></h2>
      <p><?= h($business['description'] ?: 'Selamat datang! Silakan lihat menu kami.') ?></p>
    </div>
  <?php endif; ?>
</div>

<div class="mn-content">
  <?php if (!$products): ?>
    <div class="mn-empty"><span>🍽️</span><p>Menu sedang disiapkan. Silakan kembali lagi nanti.</p></div>
  <?php else: ?>
    <?php foreach ($grouped as $cat => $items): ?>
      <section class="mn-section" id="cat-<?= h(preg_replace('/\s+/','-',strtolower($cat))) ?>">
        <div class="mn-section-head">
          <h2 class="mn-section-title"><?= h($cat) ?></h2>
        </div>
        <div class="mn-grid">
          <?php foreach ($items as $item): ?>
            <article class="mn-card">
              <?php if (!empty($item['image'])): ?>
                <img class="mn-card-img" src="<?= h($item['image']) ?>" alt="<?= h($item['product_name']) ?>">
              <?php else: ?>
                <div class="mn-card-img-placeholder">🍽️</div>
              <?php endif; ?>
              <div class="mn-card-body">
                <div class="mn-card-name"><?= h($item['product_name']) ?></div>
                <?php if ($item['description']): ?>
                  <div class="mn-card-desc"><?= h($item['description']) ?></div>
                <?php endif; ?>
                <div class="mn-card-footer">
                  <span class="mn-card-price">Rp<?= number_format((float)$item['price'],0,',','.') ?></span>
                  <?php if (!(int)$item['is_available']): ?>
                    <span class="mn-card-sold">Habis</span>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<footer class="mn-footer">Powered by <strong>QRSAJI.</strong></footer>

<script>
// Tab aktif saat klik
document.querySelectorAll('.mn-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.mn-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const id = 'cat-' + btn.dataset.cat.replace(/\s+/g,'-').toLowerCase();
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
  });
});

// Update tab aktif saat scroll
const sections = document.querySelectorAll('.mn-section');
const tabs = document.querySelectorAll('.mn-tab');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const cat = e.target.querySelector('.mn-section-title')?.textContent.trim();
      tabs.forEach(t => t.classList.toggle('active', t.dataset.cat === cat));
    }
  });
}, {threshold:0.3});
sections.forEach(s => observer.observe(s));
</script>
</body>
</html>
