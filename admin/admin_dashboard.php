<?php
session_start();
// require_once 'koneksi.php'; // uncomment dan sesuaikan path koneksi DB

// ── DATA (ganti dengan query DB) ──────────────────────────────────────────
$stats = [
  'booking_today'  => 42,
  'booking_change' => '+12%',
  'revenue_today'  => 'Rp 3,84jt',
  'revenue_proj'   => 'Rp 4,2jt',
  'revenue_change' => '+5.4%',
  'fields_active'  => 8,
  'fields_total'   => 10,
  'occupancy'      => 80,
  'pending'        => 3,
];

$courts = [
  ['id'=>1,'name'=>'Arena A',     'type'=>'Indoor',  'status'=>'occupied',    'detail'=>'Pro-League Match',  'next'=>'20:00','icon'=>'🏟️'],
  ['id'=>2,'name'=>'East Wing 1', 'type'=>'Outdoor', 'status'=>'available',   'detail'=>'Siap untuk booking','next'=>'Now',  'icon'=>'⚽'],
  ['id'=>3,'name'=>'Arena B',     'type'=>'Indoor',  'status'=>'occupied',    'detail'=>'U-18 Training Camp','next'=>'19:30','icon'=>'🏆'],
  ['id'=>4,'name'=>'West Terrace','type'=>'Outdoor', 'status'=>'maintenance', 'detail'=>'Surface cleaning',  'next'=>'+2jam','icon'=>'🔧'],
];

$upcoming = [
  ['time'=>'17:00','name'=>'Rian Pratama',    'field'=>'Arena A',    'dur'=>'2 jam','price'=>'Rp 280rb'],
  ['time'=>'18:00','name'=>'FC Muda Surabaya','field'=>'Arena B',    'dur'=>'2 jam','price'=>'Rp 280rb'],
  ['time'=>'20:00','name'=>'Dian Kusuma',     'field'=>'East Wing 1','dur'=>'1 jam','price'=>'Rp 150rb'],
  ['time'=>'21:00','name'=>'Tim Garuda FC',   'field'=>'Arena A',    'dur'=>'2 jam','price'=>'Rp 320rb'],
];

$bar_data = [
  ['day'=>'Sen','pct'=>40,'peak'=>false],
  ['day'=>'Sel','pct'=>55,'peak'=>false],
  ['day'=>'Rab','pct'=>90,'peak'=>true],
  ['day'=>'Kam','pct'=>60,'peak'=>false],
  ['day'=>'Jum','pct'=>100,'peak'=>true],
  ['day'=>'Sab','pct'=>70,'peak'=>false],
  ['day'=>'Min','pct'=>45,'peak'=>false],
];

// ── HELPERS ───────────────────────────────────────────────────────────────
function courtBadge(string $status): string {
  return match($status) {
    'occupied'    => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30">TERISI</span>',
    'available'   => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-500/15 text-green-400 border border-green-500/30">TERSEDIA</span>',
    'maintenance' => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-500/15 text-yellow-400 border border-yellow-500/30">MAINTENANCE</span>',
    default       => '',
  };
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
  <title>SmartFutsal — Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['DM Sans','sans-serif'], display: ['Bebas Neue','sans-serif'] },
          colors: {
            brand: { red:'#e8192c', red2:'#ff3344', dark:'#0d0d0d', card:'#1c1c1c', bg2:'#141414' }
          }
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
    .card-hover { transition:transform .2s, border-color .2s; }
    .card-hover:hover { transform:translateY(-2px); border-color:rgba(232,25,44,0.3); }
    .stat-top-bar { height:2px; background:linear-gradient(90deg,#e8192c,transparent); opacity:0; transition:.2s; }
    .card-hover:hover .stat-top-bar { opacity:1; }
    .progress-fill { background:linear-gradient(90deg,#e8192c,#ff3344); }
    .modal-backdrop { backdrop-filter:blur(4px); }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .modal-box { animation:fadeUp .25s ease; }
    .bar-fill { background:linear-gradient(180deg,#e8192c,rgba(232,25,44,0.3)); border-radius:4px 4px 0 0; transition:.2s; }
    .bar-fill:hover { opacity:.75; cursor:pointer; }
    .bar-dim  { background:linear-gradient(180deg,#333,#222); border-radius:4px 4px 0 0; }
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
    <a href="pengaturan.php"
       class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-400 mb-0.5 no-underline">
      <span class="text-base w-5 text-center">⚙️</span>
      <span>Pengaturan</span>
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
      <h1 class="font-display text-xl tracking-widest">Dashboard</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 rounded-lg px-3 py-2 border border-white/5 text-sm text-gray-500"
           style="background:#1a1a1a; width:220px">
        <span>🔍</span>
        <input type="text" placeholder="Cari booking, lapangan…"
               class="bg-transparent outline-none text-white text-xs w-full placeholder-gray-600" />
      </div>
      <div class="relative w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base"
           style="background:#1a1a1a">
        🔔
        <div class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-brand-red rounded-full border border-neutral-800"></div>
      </div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base"
           style="background:#1a1a1a">❓</div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="p-7 flex-1">

    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <?php
      $stat_cards = [
        ['icon'=>'📅','color'=>'red',   'badge'=>$stats['booking_change'],'badge_up'=>true, 'value'=>$stats['booking_today'],'label'=>'Total Booking Hari Ini','sub'=>'vs minggu lalu: 37 booking'],
        ['icon'=>'💰','color'=>'green', 'badge'=>$stats['revenue_change'], 'badge_up'=>true, 'value'=>$stats['revenue_today'], 'label'=>'Pendapatan Hari Ini','sub'=>'Proyeksi: '.$stats['revenue_proj'],'small'=>true],
        ['icon'=>'🏟️','color'=>'blue',  'badge'=>'+2','badge_up'=>true,  'value'=>$stats['fields_active'].'/'.$stats['fields_total'],'label'=>'Lapangan Aktif','sub_green'=>$stats['occupancy'].'% Okupansi'],
        ['icon'=>'⏳','color'=>'yellow','badge'=>$stats['pending'].' pending','badge_up'=>false,'value'=>$stats['pending'],'label'=>'Menunggu Konfirmasi','sub_yellow'=>'Perlu tindakan segera'],
      ];
      $icon_bg = ['red'=>'bg-red-500/10','green'=>'bg-green-500/10','blue'=>'bg-blue-500/10','yellow'=>'bg-yellow-500/10'];
      foreach ($stat_cards as $sc): ?>
      <div class="card-hover rounded-xl border border-white/5 p-5 relative overflow-hidden cursor-default" style="background:#1c1c1c">
        <div class="stat-top-bar absolute top-0 left-0 right-0"></div>
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 rounded-lg <?= $icon_bg[$sc['color']] ?> flex items-center justify-center text-lg">
            <?= $sc['icon'] ?>
          </div>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $sc['badge_up'] ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400' ?>">
            <?= $sc['badge'] ?>
          </span>
        </div>
        <div class="font-display <?= isset($sc['small']) ? 'text-2xl' : 'text-4xl' ?> tracking-wide leading-none"><?= $sc['value'] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $sc['label'] ?></div>
        <div class="text-xs mt-1.5 <?= isset($sc['sub_green']) ? 'text-green-400' : (isset($sc['sub_yellow']) ? 'text-yellow-400' : 'text-gray-600') ?>">
          <?= $sc['sub'] ?? $sc['sub_green'] ?? $sc['sub_yellow'] ?? '' ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- COURT STATUS + TRENDS -->
    <div class="grid gap-4 mb-6" style="grid-template-columns:2fr 1fr">

      <!-- Court Status -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="flex items-center justify-between px-5 pt-5 pb-4">
          <div>
            <div class="text-sm font-semibold">Status Lapangan Real-time</div>
            <div class="text-xs text-gray-500 mt-0.5">Diperbarui setiap 30 detik</div>
          </div>
          <div class="flex gap-1.5">
            <?php foreach(['all'=>'Semua','indoor'=>'Indoor','outdoor'=>'Outdoor'] as $k=>$v): ?>
            <button onclick="filterCourt(this,'<?= $k ?>')"
                    class="court-filter-btn text-xs px-3 py-1 rounded-md border transition
                           <?= $k==='all' ? 'bg-brand-red text-white border-brand-red' : 'text-gray-500 border-white/10 hover:bg-white/5' ?>">
              <?= $v ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2.5 px-5 pb-5">
          <?php foreach ($courts as $court): ?>
          <div class="court-card-item rounded-xl border border-white/5 overflow-hidden cursor-pointer hover:border-white/15 transition"
               style="background:#1a1a1a" data-type="<?= strtolower($court['type']) ?>">
            <div class="h-20 flex items-center justify-center text-3xl" style="background:linear-gradient(135deg,#1a1a1a,#2a2a2a)"><?= $court['icon'] ?></div>
            <div class="p-3">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-semibold"><?= $court['name'] ?></span>
                <?= courtBadge($court['status']) ?>
              </div>
              <div class="text-xs text-gray-500"><?= $court['detail'] ?></div>
              <div class="text-xs text-gray-600 mt-1.5">
                <?php if ($court['status']==='available'): ?>
                  <span class="text-green-400">✓ Siap sekarang</span>
                <?php elseif ($court['status']==='maintenance'): ?>
                  <span class="text-yellow-400">🔧 ETA: <?= $court['next'] ?></span>
                <?php else: ?>
                  <span>⏱ Tersedia <?= $court['next'] ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Trends -->
      <div class="rounded-xl border border-white/5 overflow-hidden flex flex-col" style="background:#1c1c1c">
        <div class="px-5 pt-5 pb-3">
          <div class="text-sm font-semibold">Informasi</div>
          <div class="text-xs text-gray-500 mt-0.5">Catatan owner</div>
        </div>
       
        <div class="mx-5 mb-5 rounded-xl p-3.5 border border-red-500/20"
             style="background:linear-gradient(135deg,rgba(232,25,44,0.1),rgba(232,25,44,0.04))">
          <div class="text-xs font-bold text-brand-red tracking-widest uppercase mb-1">Reminder</div>
          <div class="text-xs text-gray-300 leading-relaxed">Sabtu dan Minggu biasanya terdapat lonjakan customer, <br> Pastikan lapangan indoor sudah dibersihkan <br> Dan Follow up booking yang masih pending ! </div>
        </div>
      </div>
    </div>

    <!-- UPCOMING + REVENUE -->
    <div class="grid grid-cols-2 gap-4">

      <!-- Upcoming Bookings -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <div>
            <div class="text-sm font-semibold">Booking Berikutnya</div>
            <div class="text-xs text-gray-500 mt-0.5">24 jam ke depan</div>
          </div>
          <a href="booking.php" class="text-xs font-semibold text-brand-red hover:text-brand-red2 transition">Lihat Semua →</a>
        </div>
        <div class="px-5 pb-5">
          <?php foreach ($upcoming as $i => $bk): ?>
          <div class="flex items-center gap-3 py-3 <?= $i < count($upcoming)-1 ? 'border-b border-white/[0.04]' : '' ?>
                      cursor-pointer rounded-lg hover:bg-white/[0.03] transition -mx-2 px-2">
            <div class="min-w-[56px] text-center rounded-lg py-1.5" style="background:#1a1a1a">
              <div class="font-display text-lg leading-none"><?= $bk['time'] ?></div>
              <div class="text-xs text-gray-500">WIB</div>
            </div>
            <div class="flex-1">
              <div class="text-sm font-semibold"><?= $bk['name'] ?></div>
              <div class="text-xs text-gray-500 mt-0.5"><?= $bk['field'] ?> · <?= $bk['dur'] ?></div>
            </div>
            <div class="text-sm font-semibold text-green-400"><?= $bk['price'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Revenue -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <div>
            <div class="text-sm font-semibold">Pendapatan Bulan Ini</div>
            <div class="text-xs text-gray-500 mt-0.5">April 2026</div>
          </div>
          <a href="laporan.php" class="text-xs font-semibold text-brand-red hover:text-brand-red2 transition">Detail →</a>
        </div>
        <div class="flex items-end justify-between px-5 pb-3">
          <div>
            <div class="font-display text-4xl tracking-wide">Rp 84,2jt</div>
            <div class="text-xs text-gray-500 mt-1">Target: Rp 100jt</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-semibold text-green-400">↑ 18.3%</div>
            <div class="text-xs text-gray-500">vs bulan lalu</div>
          </div>
        </div>
        <div class="px-5 pb-4">
          <div class="flex justify-between text-xs mb-1.5">
            <span class="text-gray-500">Progress Target</span>
            <span class="font-semibold text-white">84.2%</span>
          </div>
          <div class="h-1.5 rounded-full overflow-hidden" style="background:#1a1a1a">
            <div class="progress-fill h-full rounded-full" style="width:84.2%"></div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2.5 px-5 pb-5">
          <?php foreach([['val'=>'62','lbl'=>'Selesai','clr'=>'text-white'],['val'=>'3','lbl'=>'Pending','clr'=>'text-yellow-400'],['val'=>'1','lbl'=>'Dibatalkan','clr'=>'text-red-400']] as $m): ?>
          <div class="rounded-lg text-center py-3" style="background:#1a1a1a">
            <div class="font-display text-xl <?= $m['clr'] ?>"><?= $m['val'] ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $m['lbl'] ?></div>
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
        <input type="text" placeholder="Nama lengkap"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
               style="background:#1a1a1a" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 tracking-wide">No. WhatsApp</label>
        <input type="text" placeholder="08xxxxxxxxxx"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
               style="background:#1a1a1a" />
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
        <input type="date" value="<?= date('Y-m-d') ?>"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
               style="background:#1a1a1a; color-scheme:dark" />
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
        <input type="text" placeholder="Opsional"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
               style="background:#1a1a1a" />
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
function filterCourt(btn, type) {
  document.querySelectorAll('.court-filter-btn').forEach(b => {
    b.classList.remove('bg-brand-red','text-white','border-brand-red');
    b.classList.add('text-gray-500','border-white/10');
  });
  btn.classList.add('bg-brand-red','text-white','border-brand-red');
  btn.classList.remove('text-gray-500','border-white/10');
  document.querySelectorAll('.court-card-item').forEach(card => {
    card.style.display = (type==='all' || card.dataset.type===type) ? '' : 'none';
  });
}
</script>
</body>
</html>