<?php
session_start();
// require_once 'koneksi.php'; // uncomment dan sesuaikan path koneksi DB

// ── DATA (ganti dengan query DB) ──────────────────────────────────────────
$schedule_fields = ['Arena A','Arena B','East Wing 1','West Terrace'];
$time_slots      = ['08:00','10:00','12:00','14:00','16:00','18:00','20:00'];

$schedule_slots = [
  ['field'=>'Arena A',     'label'=>'Latihan Pagi',  'start'=>'08:00','end'=>'10:00','col'=>'slot-green','row'=>1],
  ['field'=>'East Wing 1', 'label'=>'Komunitas SBY', 'start'=>'08:00','end'=>'09:00','col'=>'slot-blue', 'row'=>1],
  ['field'=>'Arena B',     'label'=>'U-18 Camp',     'start'=>'10:00','end'=>'12:00','col'=>'slot-red',  'row'=>2],
  ['field'=>'West Terrace','label'=>'Maintenance',   'start'=>'10:00','end'=>'12:00','col'=>'slot-yellow','row'=>2],
  ['field'=>'Arena A',     'label'=>'Rian Pratama',  'start'=>'12:00','end'=>'13:00','col'=>'slot-blue', 'row'=>3],
  ['field'=>'Arena B',     'label'=>'Tim Garuda FC', 'start'=>'14:00','end'=>'16:00','col'=>'slot-green','row'=>4],
  ['field'=>'Arena A',     'label'=>'Pro-League',    'start'=>'16:00','end'=>'18:00','col'=>'slot-red',  'row'=>5],
  ['field'=>'East Wing 1', 'label'=>'Dian Kusuma',   'start'=>'16:00','end'=>'17:00','col'=>'slot-blue', 'row'=>5],
  ['field'=>'Arena B',     'label'=>'FC Muda SBY',   'start'=>'18:00','end'=>'20:00','col'=>'slot-red',  'row'=>6],
  ['field'=>'East Wing 1', 'label'=>'Komunitas B',   'start'=>'18:00','end'=>'20:00','col'=>'slot-green','row'=>6],
  ['field'=>'Arena A',     'label'=>'Premium Night', 'start'=>'20:00','end'=>'22:00','col'=>'slot-green','row'=>7],
  ['field'=>'West Terrace','label'=>'Sewa Pribadi',  'start'=>'20:00','end'=>'21:00','col'=>'slot-blue', 'row'=>7],
];

// Build slot lookup [field][row]
$slot_map = [];
foreach ($schedule_slots as $s) {
  $slot_map[$s['field']][$s['row']] = $s;
}

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
  <title>SmartFutsal — Jadwal Lapangan</title>
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
    .slot-red    { background:rgba(232,25,44,0.2); color:#ff3344; border-left:3px solid #e8192c; }
    .slot-green  { background:rgba(34,197,94,0.15); color:#22c55e; border-left:3px solid #22c55e; }
    .slot-blue   { background:rgba(59,130,246,0.15); color:#3b82f6; border-left:3px solid #3b82f6; }
    .slot-yellow { background:rgba(245,158,11,0.15); color:#f59e0b; border-left:3px solid #f59e0b; }
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
        ['href'=>'admin_dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null,'active'=>true],
      ['href'=>'admin_jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null,'active'=>false],
      ['href'=>'admin_booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>3,   'active'=>false],
      ['href'=>'admin_lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null,'active'=>false],
      ['href'=>'admin_laporan.php',  'icon'=>'📈','label'=>'Laporan',         'badge'=>null,'active'=>false],
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
    <a href="pengaturan.php" class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-400 mb-0.5 no-underline">
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
      <h1 class="font-display text-xl tracking-widest">Jadwal Lapangan</h1>
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

    <div class="flex items-center justify-between mb-6">
      <div class="text-sm text-gray-500">Kelola jadwal penggunaan lapangan secara visual</div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
          <button class="w-8 h-8 rounded-lg border border-white/10 text-white text-sm flex items-center justify-center hover:bg-white/5 transition" style="background:#1c1c1c">‹</button>
          <span class="text-sm font-semibold px-2">7 – 13 April 2026</span>
          <button class="w-8 h-8 rounded-lg border border-white/10 text-white text-sm flex items-center justify-center hover:bg-white/5 transition" style="background:#1c1c1c">›</button>
        </div>
        <button onclick="openModal()" class="bg-brand-red text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-brand-red2 transition">
          + Tambah Booking
        </button>
      </div>
    </div>

    <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
      <!-- Filter Lapangan -->
      <div class="flex items-center gap-2 px-5 py-4 border-b border-white/5">
        <span class="text-xs text-gray-500 mr-1">Lapangan:</span>
        <?php $ffields = ['Semua','Arena A','Arena B','East Wing 1','West Terrace'];
        foreach ($ffields as $i => $f): ?>
        <button onclick="filterTab(this,'sched')"
                class="ftab-sched text-xs px-3 py-1 rounded-md border transition
                       <?= $i===0 ? 'bg-brand-red text-white border-brand-red' : 'text-gray-500 border-white/10 hover:bg-white/5' ?>">
          <?= $f ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Schedule Grid -->
      <div class="overflow-x-auto">
        <div class="min-w-[640px]">
          <!-- Header -->
          <div class="grid border-b border-white/5" style="grid-template-columns:72px repeat(4,1fr)">
            <div class="py-2.5 px-3 text-xs text-gray-600"></div>
            <?php foreach ($schedule_fields as $f): ?>
            <div class="py-2.5 px-3 text-xs font-semibold text-gray-400 text-center border-l border-white/5"><?= $f ?></div>
            <?php endforeach; ?>
          </div>
          <!-- Rows -->
          <?php foreach ($time_slots as $ri => $time): ?>
          <div class="grid border-b border-white/5 last:border-0" style="grid-template-columns:72px repeat(4,1fr)">
            <div class="py-3 px-3 text-xs text-gray-600 text-right"><?= $time ?></div>
            <?php foreach ($schedule_fields as $field): ?>
            <div class="py-2 px-2 min-h-[56px] border-l border-white/5">
              <?php
              $row = $ri + 1;
              if (isset($slot_map[$field][$row])):
                $s = $slot_map[$field][$row];
              ?>
              <div class="<?= $s['col'] ?> rounded-lg px-2 py-1.5 text-xs font-semibold cursor-pointer hover:opacity-80 transition">
                <div class="font-bold"><?= $s['label'] ?></div>
                <div class="opacity-70 text-xs"><?= $s['start'] ?>–<?= $s['end'] ?></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-5 px-5 py-3 border-t border-white/5">
        <span class="text-xs text-gray-600">Keterangan:</span>
        <?php foreach([['slot-red','Terisi/Booking'],['slot-green','Selesai/Konfirmasi'],['slot-blue','Komunitas'],['slot-yellow','Maintenance']] as [$cls,$lbl]): ?>
        <div class="flex items-center gap-1.5 text-xs text-gray-500">
          <div class="w-3 h-3 rounded <?= $cls ?>"></div><?= $lbl ?>
        </div>
        <?php endforeach; ?>
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
function filterTab(btn, group) {
  document.querySelectorAll(`.ftab-${group}`).forEach(b => {
    b.classList.remove('bg-brand-red','text-white','border-brand-red');
    b.classList.add('text-gray-500','border-white/10');
  });
  btn.classList.add('bg-brand-red','text-white','border-brand-red');
  btn.classList.remove('text-gray-500','border-white/10');
}
</script>
</body>
</html>