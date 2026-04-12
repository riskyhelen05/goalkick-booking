<?php
session_start();
include '../koneksi.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
// require_once 'koneksi.php'; // uncomment dan sesuaikan path koneksi DB

// ── TANGGAL ───────────────────────────────────────────────────────────────
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartFutsal — Pengaturan</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['DM Sans','sans-serif'], display: ['Bebas Neue','sans-serif'] },
          colors: { brand: { red:'#e8192c', red2:'#ff3344', dark:'#0d0d0d', card:'#1c1c1c', bg2:'#141414' } }
        }
      }
    }
  </script>
  <style>
    body { background:#0d0d0d; color:#f5f5f5; }
    ::-webkit-scrollbar { width:5px; height:5px; }
    ::-webkit-scrollbar-track { background:transparent; }
    ::-webkit-scrollbar-thumb { background:#333; border-radius:3px; }
    .sidebar-link { transition:all .15s; }
    .sidebar-link:hover { background:rgba(255,255,255,0.05); color:#fff; }
    .sidebar-link.active { background:rgba(232,25,44,0.15); color:#ff3344; border:1px solid rgba(232,25,44,0.25); }
    .modal-backdrop { backdrop-filter:blur(4px); }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .modal-box { animation:fadeUp .25s ease; }
  </style>
</head>
<body class="font-sans flex min-h-screen">

<!-- ═══════════════════════════════ SIDEBAR ═══════════════════════════════ -->
<aside class="fixed top-0 left-0 h-screen flex flex-col z-50 border-r border-white/5"
       style="background:#141414; width:240px">
  <div class="px-6 py-7 border-b border-white/5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-brand-red flex items-center justify-center text-lg">⚽</div>
      <div>
        <div class="font-display text-xl tracking-wider leading-none">Smart<span class="text-brand-red">Futsal</span></div>
        <div class="text-xs text-gray-500 tracking-widest uppercase mt-0.5">Admin Panel</div>
      </div>
    </div>
  </div>
  <nav class="flex-1 px-3 py-4 overflow-y-auto">
    <div class="text-xs text-gray-600 tracking-widest uppercase px-3 mb-2">Menu Utama</div>
    <?php
    $nav = [
      ['href'=>'dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null,'active'=>false],
      ['href'=>'jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null,'active'=>false],
      ['href'=>'booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>3,   'active'=>false],
      ['href'=>'lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null,'active'=>false],
      ['href'=>'laporan.php',  'icon'=>'📈','label'=>'Laporan',         'badge'=>null,'active'=>false],
    ];
    foreach ($nav as $n): ?>
    <a href="<?= $n['href'] ?>"
       class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-400 mb-0.5 no-underline <?= $n['active'] ? 'active' : '' ?>">
      <span class="text-base w-5 text-center"><?= $n['icon'] ?></span>
      <span class="flex-1"><?= $n['label'] ?></span>
      <?php if ($n['badge']): ?>
        <span class="bg-brand-red text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= $n['badge'] ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <div class="text-xs text-gray-600 tracking-widest uppercase px-3 mb-2 mt-4">Pengaturan</div>
    <a href="pengaturan.php" class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-400 mb-0.5 no-underline active">
      <span class="text-base w-5 text-center">⚙️</span><span>Pengaturan</span>
    </a>
  </nav>
  <div class="px-3 py-4 border-t border-white/5">
    <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg cursor-pointer hover:bg-white/5 transition">
      <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white"
           style="background:linear-gradient(135deg,#e8192c,#800010)">AD</div>
      <div>
        <div class="text-sm font-semibold text-white">Admin Pitch</div>
        <div class="text-xs text-gray-500">Super Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- ════════════════════════════ MAIN CONTENT ════════════════════════════ -->
<div class="flex-1 flex flex-col" style="margin-left:240px">

  <!-- TOPBAR -->
  <header class="sticky top-0 z-40 h-16 flex items-center justify-between px-7 border-b border-white/5" style="background:#141414">
    <div>
      <h1 class="font-display text-xl tracking-widest">Pengaturan</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 rounded-lg px-3 py-2 border border-white/5 text-sm text-gray-500"
           style="background:#1a1a1a; width:220px">
        <span>🔍</span>
        <input type="text" placeholder="Cari booking, lapangan…" class="bg-transparent outline-none text-white text-xs w-full placeholder-gray-600" />
      </div>
      <div class="relative w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base" style="background:#1a1a1a">
        🔔<div class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-brand-red rounded-full border border-neutral-800"></div>
      </div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base" style="background:#1a1a1a">❓</div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="p-7 flex-1">

    <div class="text-sm text-gray-500 mb-6">Konfigurasi sistem dan profil tempat</div>

    <div class="grid grid-cols-2 gap-4">

      <!-- Informasi Tempat -->
      <div class="rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
        <div class="text-sm font-semibold mb-5">Informasi Tempat</div>
        <div class="flex flex-col gap-4">
          <?php
          $fields_info = [
            ['label'=>'Nama Tempat',   'type'=>'text', 'val'=>'SmartFutsal Surabaya'],
            ['label'=>'Alamat Lengkap','type'=>'text', 'val'=>'Jl. Raya Darmo No. 88, Surabaya'],
            ['label'=>'No. WhatsApp',  'type'=>'text', 'val'=>'08112345678'],
            ['label'=>'Email Kontak',  'type'=>'email','val'=>'admin@smartfutsal.id'],
          ];
          foreach ($fields_info as $fi): ?>
          <div>
            <label class="block text-xs text-gray-500 mb-1.5 tracking-wide"><?= $fi['label'] ?></label>
            <input type="<?= $fi['type'] ?>" value="<?= htmlspecialchars($fi['val']) ?>"
                   class="w-full rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
                   style="background:#1a1a1a" />
          </div>
          <?php endforeach; ?>
          <div>
            <label class="block text-xs text-gray-500 mb-1.5 tracking-wide">Jam Operasional</label>
            <div class="flex gap-2 items-center">
              <input type="time" value="06:00"
                     class="flex-1 rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
                     style="background:#1a1a1a; color-scheme:dark" />
              <span class="text-gray-500 text-sm">—</span>
              <input type="time" value="23:00"
                     class="flex-1 rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
                     style="background:#1a1a1a; color-scheme:dark" />
            </div>
          </div>
          <button class="w-full py-2.5 rounded-lg bg-brand-red text-white text-sm font-semibold hover:bg-brand-red2 transition mt-1">
            Simpan Perubahan
          </button>
        </div>
      </div>

      <!-- Kanan -->
      <div class="flex flex-col gap-4">

        <!-- Pengaturan Booking -->
        <div class="rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
          <div class="text-sm font-semibold mb-5">Pengaturan Booking</div>
          <div class="flex flex-col gap-4">
            <?php
            $selects = [
              ['label'=>'Minimum Durasi Booking','opts'=>['1 Jam','2 Jam'],'sel'=>'1 Jam'],
              ['label'=>'Maks. Booking ke Depan','opts'=>['7 Hari','14 Hari','30 Hari'],'sel'=>'14 Hari'],
              ['label'=>'Konfirmasi Booking','opts'=>['Otomatis (Auto-confirm)','Manual (Admin Konfirmasi)'],'sel'=>'Otomatis (Auto-confirm)'],
            ];
            foreach ($selects as $s): ?>
            <div>
              <label class="block text-xs text-gray-500 mb-1.5 tracking-wide"><?= $s['label'] ?></label>
              <select class="w-full rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
                <?php foreach ($s['opts'] as $o): ?>
                <option <?= $o===$s['sel']?'selected':'' ?>><?= $o ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endforeach; ?>
            <div>
              <label class="block text-xs text-gray-500 mb-1.5 tracking-wide">Deposit (%)</label>
              <input type="number" value="50" min="0" max="100"
                     class="w-full rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
                     style="background:#1a1a1a" />
            </div>
            <button class="w-full py-2.5 rounded-lg bg-brand-red text-white text-sm font-semibold hover:bg-brand-red2 transition">
              Simpan Perubahan
            </button>
          </div>
        </div>

        <!-- Info Sistem -->
        <div class="rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
          <div class="text-sm font-semibold mb-4">Info Sistem</div>
          <?php
          $sysinfo = [
            ['label'=>'Versi Aplikasi','val'=>'v1.0.0'],
            ['label'=>'PHP Version',   'val'=>PHP_VERSION],
            ['label'=>'Server Time',   'val'=>date('H:i:s')],
            ['label'=>'Tanggal Server','val'=>date('d M Y')],
          ];
          foreach ($sysinfo as $si): ?>
          <div class="flex justify-between items-center py-2 border-b border-white/[0.04] last:border-0 text-sm">
            <span class="text-gray-500"><?= $si['label'] ?></span>
            <span class="font-medium font-mono text-xs text-green-400"><?= $si['val'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>

  </main>
</div>

<!-- FAB -->
<button onclick="openModal()"
  class="fixed bottom-7 right-7 rounded-full bg-brand-red text-white text-2xl flex items-center justify-center shadow-lg z-40 hover:scale-110 transition-transform"
  style="width:52px;height:52px;box-shadow:0 4px 20px rgba(232,25,44,0.4)">＋</button>

<!-- MODAL BOOKING -->
<div id="modal" class="modal-backdrop fixed inset-0 bg-black/70 z-50 hidden items-center justify-center"
     onclick="if(event.target===this)closeModal()">
  <div class="modal-box rounded-2xl p-7 w-[480px] max-w-[95vw] border border-white/5" style="background:#1c1c1c">
    <div class="font-display text-2xl tracking-widest mb-1">Tambah Booking</div>
    <div class="text-xs text-gray-500 mb-6">Buat reservasi lapangan baru</div>
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">Nama Pelanggan</label>
        <input type="text" placeholder="Nama lengkap" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">No. WhatsApp</label>
        <input type="text" placeholder="08xxxxxxxxxx" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
      <div class="flex flex-col gap-1.5 col-span-2">
        <label class="text-xs text-gray-500 tracking-wide">Pilih Lapangan</label>
        <select class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <option>Arena A (Indoor · Rp 140rb/jam)</option>
          <option>Arena B (Indoor · Rp 160rb/jam)</option>
          <option>East Wing 1 (Outdoor · Rp 100rb/jam)</option>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">Tanggal</label>
        <input type="date" value="<?= date('Y-m-d') ?>" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a; color-scheme:dark" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">Jam Mulai</label>
        <select class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <?php foreach(['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'] as $h): ?>
          <option <?= $h==='18:00'?'selected':'' ?>><?= $h ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">Durasi</label>
        <select class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <option>1 Jam</option><option selected>2 Jam</option><option>3 Jam</option>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">Catatan</label>
        <input type="text" placeholder="Opsional" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
    </div>
    <div class="flex justify-between items-center rounded-lg px-4 py-3 text-sm mt-1" style="background:#1a1a1a">
      <span class="text-gray-500">Estimasi Total</span>
      <span class="font-bold text-green-400">Rp 280.000</span>
    </div>
    <div class="flex gap-2.5 mt-5">
      <button onclick="closeModal()" class="flex-1 py-2.5 rounded-lg border border-white/10 text-gray-400 text-sm hover:bg-white/5 transition">Batal</button>
      <button onclick="closeModal()" class="flex-[2] py-2.5 rounded-lg bg-brand-red text-white text-sm font-semibold hover:bg-brand-red2 transition">✓ Buat Booking</button>
    </div>
  </div>
</div>

<script>
function openModal() { const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal() { const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
</script>
</body>
</html>