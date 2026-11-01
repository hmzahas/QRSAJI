(() => {
  // Ambil konfigurasi global yang dikirim dari PHP (token CSRF, ID usaha, daftar kategori)
  const config = window.QRSAJI || {csrf:'',businessId:0,categories:[]};

  // Shortcut untuk querySelector dan querySelectorAll
  const $ = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => [...root.querySelectorAll(s)];

  // Elemen-elemen utama modal (popup form)
  const backdrop = $('#modalBackdrop');  // Latar belakang gelap di belakang modal
  const form     = $('#modalForm');      // Form di dalam modal
  const fields   = $('#modalFields');    // Area tempat field-field form dirender
  const title    = $('#modalTitle');     // Judul modal
  const message  = $('#modalMessage');   // Area pesan error di dalam modal

  // Status halaman saat ini
  let currentSection = 'overview'; // Bagian dashboard yang sedang aktif
  let modalKind = '';               // Jenis modal yang sedang terbuka (business/product/category)
  let editId = null;                // ID data yang sedang diedit (null kalau tambah baru)

  // Fungsi untuk mengamankan teks sebelum dimasukkan ke HTML (mencegah XSS)
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

  // Tampilkan notifikasi di bagian atas halaman (hijau = sukses, merah = error)
  const showNotice = (text, type='success') => {
    const n = $('#notice');
    n.textContent = text;
    n.className = 'notice ' + type;
    n.hidden = false;
    n.scrollIntoView({behavior:'smooth',block:'nearest'});
  };

  // Pindah ke bagian dashboard tertentu (overview, business, products, dll)
  const switchSection = (name) => {
    const target = $('#section-' + name);
    if (!target) return;
    currentSection = name;

    // Tampilkan section yang dipilih, sembunyikan yang lain
    $$('.view-section').forEach(s => s.classList.toggle('active', s === target));

    // Tandai tombol navigasi yang aktif di sidebar
    $$('.nav-item[data-section]').forEach(b => b.classList.toggle('active', b.dataset.section === name));

    // Tutup sidebar kalau sedang terbuka (di layar kecil)
    $('#sidebar')?.classList.remove('open');
    $('#sidebarOverlay')?.classList.remove('show');

    // Perbarui judul di topbar sesuai section yang aktif
    const names = {overview:'Dashboard Pemilik',business:'Profil Usaha',products:'Item Menu',categories:'Kategori',qr:'Kode QR',settings:'Pengaturan'};
    const topTitle = $('.topbar-title');
    if (topTitle) topTitle.textContent = names[name] || 'Dashboard Pemilik';

    // Perbarui URL di browser tanpa reload halaman
    if (location.hash !== '#section-' + name) history.replaceState(null,'','#section-' + name);

    // Scroll ke atas halaman
    window.scrollTo({top:0,behavior:'smooth'});
  };

  // Pasang event klik pada semua tombol navigasi sidebar
  $$('.nav-item[data-section]').forEach(btn => btn.addEventListener('click', () => switchSection(btn.dataset.section)));

  // Pasang event klik pada tombol yang punya atribut data-go (shortcut pindah section)
  $$('[data-go]').forEach(btn => btn.addEventListener('click', () => switchSection(btn.dataset.go)));

  // Tombol hamburger untuk membuka/menutup sidebar di layar kecil

  $('#mobileMenu')?.addEventListener('click', () => {
    const isOpen = $('#sidebar').classList.toggle('open');
    $('#sidebarOverlay')?.classList.toggle('show', isOpen);
  });

  $('#sidebarOverlay')?.addEventListener('click', () => {
    $('#sidebar').classList.remove('open');
    $('#sidebarOverlay').classList.remove('show');
  });





  // Kalau pengguna ganti usaha di dropdown overview, reload halaman dengan business_id baru
  $('#businessSwitcher')?.addEventListener('change', e => {
    const url = new URL(location.href);
    url.searchParams.set('business_id', e.target.value);
    url.hash = '#section-' + currentSection;
    location.href = url.toString();
  });

  // Kalau pengguna ganti usaha di dropdown QR, reload halaman dengan business_id baru
  $('#qrBusinessSwitcher')?.addEventListener('change', e => {
    const url = new URL(location.href);
    url.searchParams.set('business_id', e.target.value);
    url.hash = '#section-qr';
    location.href = url.toString();
  });

  // Buka section sesuai hash URL saat halaman pertama kali dimuat
  const startSection = (location.hash || '').replace('#section-','');
  if (startSection && $('#section-' + startSection)) switchSection(startSection);

  // Tutup modal dan reset semua isinya
  const closeModal = () => { backdrop.hidden = true; form.reset(); fields.innerHTML=''; message.hidden=true; message.textContent=''; };
  $('#modalClose').addEventListener('click', closeModal);
  $('#modalCancel').addEventListener('click', closeModal);

  // Klik di luar area modal juga menutup modal
  backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });

  // Tekan tombol Escape untuk menutup modal
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !backdrop.hidden) closeModal(); });

  // Daftar nama hari dalam seminggu
  const DAYS = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

  // Buat semua pilihan waktu dari 00.00 sampai 23.30 dengan interval 30 menit
  const timeOptions = () => {
    let opts = '';
    for (let h = 0; h < 24; h++) {
      for (let m of ['00','30']) {
        const hh = String(h).padStart(2,'0');
        opts += `<option value="${hh}.${m}">${hh}.${m}</option>`;
      }
    }
    return opts;
  };

  // Bangun HTML untuk field "Jam Operasional" berdasarkan nilai yang sudah ada
  const buildHoursField = (currentValue = '') => {
    // Parsing nilai yang sudah ada, contoh: "Senin–Jumat, 09.00–21.00"
    let selectedDays = [];
    let openTime = '09.00', closeTime = '21.00';
    if (currentValue) {
      // Ambil jam buka dan tutup dari teks
      const match = currentValue.match(/([\d]{2}\.[\d]{2})\s*[–-]\s*([\d]{2}\.[\d]{2})/);
      if (match) { openTime = match[1]; closeTime = match[2]; }

      // Tentukan hari mana yang aktif
      const dayPart = currentValue.split(',')[0].trim();
      if (dayPart === 'Setiap hari') {
        selectedDays = [...DAYS]; // Semua hari dipilih
      } else {
        selectedDays = DAYS.filter(d => dayPart.includes(d));
      }
    }

    // Buat tombol pill untuk setiap hari (aktif kalau sudah dipilih sebelumnya)
    const dayChecks = DAYS.map(d => `
      <label class="oh-day ${selectedDays.includes(d)?'oh-day--on':''}"><input type="checkbox" value="${d}" ${selectedDays.includes(d)?'checked':''}>${d}</label>
    `).join('');

    return `
      <div class="field full" id="oh-wrap">
        <label>Jam operasional</label>
        <div class="oh-days">${dayChecks}</div>
        <div class="oh-times">
          <div class="oh-time-group">
            <span>Buka</span>
            <select id="oh-open">${timeOptions()}</select>
          </div>
          <span class="oh-sep">–</span>
          <div class="oh-time-group">
            <span>Tutup</span>
            <select id="oh-close">${timeOptions()}</select>
          </div>
        </div>
        <!-- Preview teks jam operasional yang akan disimpan -->
        <div class="oh-preview" id="oh-preview"></div>
        <!-- Input tersembunyi yang menyimpan nilai akhir untuk dikirim ke server -->
        <input type="hidden" name="opening_hours" id="oh-hidden">
      </div>`;
  };

  // Aktifkan interaktivitas field jam operasional setelah HTML-nya ada di DOM
  const initHoursField = () => {
    const wrap = $('#oh-wrap');
    if (!wrap) return;

    const openSel  = $('#oh-open', wrap);
    const closeSel = $('#oh-close', wrap);
    const hidden   = $('#oh-hidden', wrap);
    const preview  = $('#oh-preview', wrap);

    // Fungsi untuk set nilai dropdown ke opsi yang paling cocok
    const setSelectVal = (sel, val) => {
      const opt = [...sel.options].find(o => o.value === val);
      if (opt) sel.value = opt.value;
    };

    // Set nilai awal dropdown jam buka dan tutup
    setSelectVal(openSel, openSel.dataset.init || '09.00');
    setSelectVal(closeSel, closeSel.dataset.init || '21.00');

    // Fungsi update: dijalankan setiap kali hari atau jam berubah
    const update = () => {
      // Kumpulkan hari-hari yang dicentang
      const checked = [...wrap.querySelectorAll('.oh-day input:checked')].map(i => i.value);

      // Perbarui tampilan visual pill hari (aktif/tidak aktif)
      wrap.querySelectorAll('.oh-day').forEach(l => {
        l.classList.toggle('oh-day--on', l.querySelector('input').checked);
      });

      // Tentukan teks hari: "Setiap hari", nama hari, atau kosong
      let dayStr = '';
      if (checked.length === 0) { dayStr = ''; }
      else if (checked.length === DAYS.length) { dayStr = 'Setiap hari'; }
      else { dayStr = checked.join(', '); }

      // Gabungkan hari dan jam menjadi satu teks
      const timeStr = `${openSel.value}–${closeSel.value}`;
      const result  = dayStr ? `${dayStr}, ${timeStr}` : '';

      // Simpan ke input tersembunyi dan tampilkan di preview
      hidden.value      = result;
      preview.textContent = result || '—';
    };

    // Pasang event listener ke semua checkbox hari dan dropdown jam
    wrap.querySelectorAll('.oh-day input').forEach(cb => cb.addEventListener('change', update));
    openSel.addEventListener('change', update);
    closeSel.addEventListener('change', update);

    // Jalankan update pertama kali untuk mengisi preview
    update();
  };

  // Fungsi pembantu untuk membuat HTML satu field form
  const field = (label, name, value='', type='text', opts={}) => {
    const full        = opts.full ? ' full' : '';
    const required    = opts.required ? ' required' : '';
    const placeholder = opts.placeholder ? ` placeholder="${esc(opts.placeholder)}"` : '';

    if (type === 'textarea') return `<div class="field${full}"><label for="${name}">${label}</label><textarea id="${name}" name="${name}"${required}${placeholder}>${esc(value)}</textarea></div>`;

    if (type === 'select') {
      const options = opts.options || [];
      return `<div class="field${full}"><label for="${name}">${label}</label><select id="${name}" name="${name}"><option value="">Tanpa kategori</option>${options.map(o=>`<option value="${o.id}" ${String(value)===String(o.id)?'selected':''}>${esc(o.name)}</option>`).join('')}</select></div>`;
    }

    if (type === 'checkbox') return `<div class="field${full}"><label class="checkbox-line"><input type="checkbox" name="${name}" ${value ? 'checked':''}> ${label}</label></div>`;

    if (type === 'file') return `<div class="field${full}"><label for="${name}">${label}</label><input id="${name}" name="${name}" type="file" accept="image/jpeg,image/png,image/webp"><small class="hint">JPG, PNG, WEBP; maksimal 4 MB.</small></div>`;

    // Default: input teks biasa
    return `<div class="field${full}"><label for="${name}">${label}</label><input id="${name}" name="${name}" type="${type}" value="${esc(value)}"${required}${placeholder} ${type==='number'?'min="0" step="0.01"':''}></div>`;
  };

  // Buka modal form untuk tambah atau edit data
  const openModal = (kind, mode, data={}) => {
    modalKind = kind;
    editId    = data.id || null;

    // Reset form dan bersihkan isi sebelumnya
    form.reset(); fields.innerHTML=''; message.hidden=true; message.textContent='';

    const editing = mode === 'edit';
    const labels  = {business:'Profil Usaha',product:'Item Menu',category:'Kategori'};
    title.textContent = (editing ? 'Edit ' : 'Tambah ') + labels[kind];

    // Render field sesuai jenis modal
    if (kind === 'business') {
      // Field untuk form profil usaha
      fields.innerHTML =
        field('Nama usaha *','business_name',data.business_name || '', 'text',{required:true,full:true,placeholder:'Contoh: Ruang Rasa'}) +
        field('Deskripsi usaha','description',data.description || '','textarea',{full:true,placeholder:'Ceritakan usaha kamu secara singkat'}) +
        field('Alamat usaha','address',data.address || '','textarea',{full:true,placeholder:'Alamat atau lokasi usaha'}) +
        field('Nomor WhatsApp','whatsapp',data.whatsapp || '','tel',{placeholder:'Contoh: 628123456789'}) +
        // Field upload logo — tampilkan preview kalau sudah ada logo sebelumnya
        `<div class="field full"><label>Logo Restoran</label>${data.logo ? `<img src="${esc(data.logo)}" class="business-logo-preview">` : ''}<input type="file" name="business_logo" accept="image/jpeg,image/png,image/webp"><small class="hint">JPG, PNG, WEBP · maks. 2 MB. Kosongkan jika tidak ingin mengubah foto.</small></div>` +
        `<div class="field full"><label>Banner Menu</label>${data.banner ? `<img src="${esc(data.banner)}" class="business-banner-preview">` : ''}<input type="file" name="business_banner" accept="image/jpeg,image/png,image/webp"><small class="hint">Foto banner yang tampil di halaman menu publik. JPG, PNG, WEBP · maks. 4 MB.</small></div>` +
        buildHoursField(data.opening_hours || '');

    } else if (kind === 'category') {
      // Field untuk form kategori
      fields.innerHTML = field('Nama kategori *','category_name',data.name || data.category_name || '','text',{required:true,full:true,placeholder:'Contoh: Makanan'});

    } else if (kind === 'product') {
      // Field untuk form produk/menu
      fields.innerHTML =
        field('Nama menu *','product_name',data.product_name || '','text',{required:true,full:true,placeholder:'Contoh: Nasi Goreng Spesial'}) +
        field('Kategori','menu_id',data.menu_id || '', 'select',{options:config.categories}) +
        field('Harga (Rp) *','price',data.price || '','number',{required:true,placeholder:'25000'}) +
        field('Foto produk','product_image','','file',{full:true}) +
        field('Deskripsi','product_description',data.description || '','textarea',{full:true,placeholder:'Deskripsi singkat produk'}) +
        field('Produk tersedia','is_available',data.is_available === undefined ? true : Number(data.is_available) === 1,'checkbox',{full:true});
    }

    // Tambahkan input tersembunyi untuk action (create/update)
    const hiddenAction = document.createElement('input');
    hiddenAction.type='hidden'; hiddenAction.name='action'; hiddenAction.value=kind+'_'+(editing?'update':'create');
    form.appendChild(hiddenAction);

    // Kalau mode edit, tambahkan input tersembunyi untuk ID data yang diedit
    if (editId) {
      const hiddenId = document.createElement('input');
      hiddenId.type='hidden';
      hiddenId.name=kind==='business'?'business_id':kind==='product'?'product_id':'category_id';
      hiddenId.value=editId;
      form.appendChild(hiddenId);
    }

    // Tampilkan modal
    backdrop.hidden = false;

    // Setelah DOM siap, inisialisasi field jam operasional dan set nilai awal
    setTimeout(() => {
      initHoursField();

      // Set nilai dropdown jam sesuai data yang sudah ada
      const wrap = $('#oh-wrap');
      if (wrap) {
        const currentValue = (modalKind === 'business' && data.opening_hours) ? data.opening_hours : '';
        const match = currentValue.match(/([\d]{2}\.[\d]{2})\s*[–-]\s*([\d]{2}\.[\d]{2})/);
        if (match) {
          const setVal = (sel, val) => { const o = [...sel.options].find(x => x.value === val); if(o) sel.value = o.value; };
          setVal($('#oh-open', wrap), match[1]);
          setVal($('#oh-close', wrap), match[2]);
          // Trigger change agar preview ikut diperbarui
          $('#oh-open', wrap).dispatchEvent(new Event('change'));
        }
      }

      // Fokus ke field pertama yang bisa diisi
      $('input:not([type=hidden]),textarea,select',form)?.focus();
    }, 50);
  };

  // Pasang event klik pada tombol "Tambah" untuk masing-masing jenis data
  $('#addBusinessBtn')?.addEventListener('click', () => openModal('business','create'));
  $('#addProductBtn')?.addEventListener('click', () => openModal('product','create'));
  $('#addCategoryBtn')?.addEventListener('click', () => openModal('category','create'));

  // Pasang event klik pada tombol "Edit" untuk profil usaha
  $$('[data-edit-business]').forEach(btn => btn.addEventListener('click', () => {
    const card = btn.closest('.business-card');

    // Ambil data dari elemen HTML yang sudah dirender di halaman
    const name        = $('.business-title h2',card)?.textContent.trim() || '';
    const details     = $$('.business-details small',card).map(x=>x.textContent.trim());
    const description = $('.business-details>p',card)?.textContent.trim() || '';

    // Susun objek data untuk dikirim ke openModal
    const values = {
      id:btn.dataset.editBusiness,
      business_name:name,
      description,
      logo:btn.dataset.logo||'',
      banner:btn.dataset.banner||'',
      address:details.find(x=>x.startsWith('⌖ '))?.slice(2)||'',
      whatsapp:details.find(x=>x.startsWith('☏ '))?.slice(2)||'',
      opening_hours:details.find(x=>x.startsWith('◷ '))?.slice(2)||''
    };
    openModal('business','edit',values);
  }));

  // Pasang event klik pada tombol "Edit" untuk kategori
  $$('[data-edit-category]').forEach(btn => btn.addEventListener('click', () => openModal('category','edit',{id:btn.dataset.editCategory,name:btn.dataset.name})));

  // Pasang event klik pada tombol "Edit" untuk produk
  $$('[data-edit-product]').forEach(btn => btn.addEventListener('click', () => {
    let data={};
    try { data=JSON.parse(btn.dataset.product || '{}'); } catch(e) {}
    data.id=btn.dataset.editProduct;
    openModal('product','edit',data);
  }));

  // Kirim data form ke server menggunakan fetch (AJAX)
  const postAction = async (data) => {
    // Tambahkan token CSRF dan business_id ke setiap request
    data.append('csrf_token',config.csrf);
    if (!data.has('business_id') && config.businessId) data.append('business_id',config.businessId);

    const response = await fetch('dashboard.php?ajax=1',{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});

    let result;
    try { result=await response.json(); } catch(e) { throw new Error('Respons server tidak valid. Periksa error PHP.'); }

    if (!response.ok || !result.ok) throw new Error(result.message || 'Permintaan gagal.');
    return result;
  };

  // Tangani submit form modal
  form.addEventListener('submit', async e => {
    e.preventDefault(); // Cegah reload halaman

    const submit = $('#modalSubmit');
    submit.disabled=true; submit.textContent='Menyimpan...'; // Nonaktifkan tombol saat proses

    try {
      const fd = new FormData(form);
      const result = await postAction(fd);

      closeModal();
      showNotice(result.message,'success');

      // Reload halaman setelah 350ms agar data terbaru tampil
      const hash = '#section-' + currentSection;
      setTimeout(() => { location.hash=hash; location.reload(); },350);

    } catch(err) {
      // Tampilkan pesan error di dalam modal
      message.textContent=err.message;
      message.hidden=false;
    } finally {
      // Aktifkan kembali tombol simpan
      submit.disabled=false; submit.textContent='Simpan';
    }
  });

  // Tampilkan konfirmasi hapus dan kirim request delete ke server
  const confirmDelete = async (kind,id,name) => {
    const label = kind==='business'?'profil usaha':kind==='product'?'produk':'kategori';

    // Minta konfirmasi dari pengguna sebelum menghapus
    if (!window.confirm(`Hapus ${label} "${name || ''}"? Tindakan ini tidak dapat dibatalkan.`)) return;

    const fd=new FormData();
    fd.append('action',kind+'_delete');
    fd.append(kind==='business'?'business_id':kind==='product'?'product_id':'category_id',id);

    try {
      const result=await postAction(fd);
      showNotice(result.message,'success');
      const hash='#section-'+currentSection;
      setTimeout(()=>{location.hash=hash;location.reload();},350);
    } catch(err) { showNotice(err.message,'error'); }
  };

  // Pasang event klik pada semua tombol "Hapus"
  $$('[data-delete-business]').forEach(btn=>btn.addEventListener('click',()=>confirmDelete('business',btn.dataset.deleteBusiness,btn.dataset.name)));
  $$('[data-delete-product]').forEach(btn=>btn.addEventListener('click',()=>confirmDelete('product',btn.dataset.deleteProduct,btn.dataset.name)));
  $$('[data-delete-category]').forEach(btn=>btn.addEventListener('click',()=>confirmDelete('category',btn.dataset.deleteCategory,btn.dataset.name)));

  // Filter produk berdasarkan status (semua/aktif/habis)
  $$('.filter-pill').forEach(btn=>btn.addEventListener('click',()=>{
    $$('.filter-pill').forEach(b=>b.classList.toggle('active',b===btn));
    applyProductFilters();
  }));

  // Filter produk berdasarkan kata kunci pencarian
  $('#productSearch')?.addEventListener('input',applyProductFilters);

  function applyProductFilters(){
    const filter=$('.filter-pill.active')?.dataset.filter || 'all';
    const query=($('#productSearch')?.value || '').toLowerCase().trim();

    // Sembunyikan atau tampilkan baris produk sesuai filter dan kata kunci
    $$('[data-product-row]').forEach(row=>{
      const available=row.dataset.available==='1';
      const matchesFilter=filter==='all'||(filter==='available'&&available)||(filter==='unavailable'&&!available);
      row.hidden=!(matchesFilter && (row.dataset.search||'').includes(query));
    });
  }

  // Tombol salin URL menu publik ke clipboard
  $('#copyUrlBtn')?.addEventListener('click', async ()=>{
    const input=$('#publicUrl');
    try { await navigator.clipboard.writeText(input.value); showNotice('Alamat menu berhasil disalin.'); }
    catch(e) { input.select(); document.execCommand('copy'); showNotice('Alamat menu disalin.'); }
  });

  // Tombol unduh QR (unduh ditangani oleh atribut download di HTML)
  $('#downloadQrBtn')?.addEventListener('click',()=>{});
})();
