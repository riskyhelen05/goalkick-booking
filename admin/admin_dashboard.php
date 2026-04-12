<?php
session_start();
include '../koneksi.php';

// ── HELPERS ───────────────────────────────────────────────────────────────
function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function rupiahShort($n) {
    if ($n >= 1000000) return 'Rp ' . number_format($n/1000000, 1, ',', '.') . 'jt';
    if ($n >= 1000)    return 'Rp ' . number_format($n/1000, 0, ',', '.') . 'rb';
    return 'Rp ' . $n;
}

// ── TANGGAL ───────────────────────────────────────────────────────────────
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');
$today     = date('Y-m-d');
$bln_awal  = date('Y-m-01');
$bln_akhir = date('Y-m-t');

// ── STAT: BOOKING HARI INI ────────────────────────────────────────────────
$r = $koneksi->query("SELECT COUNT(*) as c FROM booking WHERE tanggal = '$today'");
$booking_today = $r->fetch_assoc()['c'];

// Minggu lalu
$last_week = date('Y-m-d', strtotime('-7 days'));
$r2 = $koneksi->query("SELECT COUNT(*) as c FROM booking WHERE tanggal = '$last_week'");
$booking_last = $r2->fetch_assoc()['c'];
$booking_change = $booking_last > 0 ? round(($booking_today - $booking_last) / $booking_last * 100) : 0;
$booking_change_str = ($booking_change >= 0 ? '+' : '') . $booking_change . '%';

// ── STAT: PENDAPATAN HARI INI ─────────────────────────────────────────────
$r = $koneksi->query("
    SELECT COALESCE(SUM(p.jumlah),0) as total
    FROM pembayaran p
    JOIN booking b ON p.booking_id = b.id
    WHERE p.status = 'berhasil' AND DATE(p.paid_at) = '$today'
");
$revenue_today = $r->fetch_assoc()['total'];

// Proyeksi (rata-rata harian * sisa hari)
$r = $koneksi->query("
    SELECT COALESCE(SUM(p.jumlah),0) as total
    FROM pembayaran p
    JOIN booking b ON p.booking_id = b.id
    WHERE p.status = 'berhasil' AND b.tanggal BETWEEN '$bln_awal' AND '$today'
");
$revenue_mtd = $r->fetch_assoc()['total'];
$day_of_month = intval(date('j'));
$days_in_month = intval(date('t'));
$avg_daily = $day_of_month > 0 ? $revenue_mtd / $day_of_month : 0;
$revenue_proj = $avg_daily * $days_in_month;

// Perubahan vs kemarin
$yesterday = date('Y-m-d', strtotime('-1 day'));
$r = $koneksi->query("
    SELECT COALESCE(SUM(p.jumlah),0) as total
    FROM pembayaran p
    JOIN booking b ON p.booking_id = b.id
    WHERE p.status = 'berhasil' AND DATE(p.paid_at) = '$yesterday'
");
$revenue_yest = $r->fetch_assoc()['total'];
$rev_change = $revenue_yest > 0 ? round(($revenue_today - $revenue_yest) / $revenue_yest * 100, 1) : 0;
$rev_change_str = ($rev_change >= 0 ? '+' : '') . $rev_change . '%';

// ── STAT: LAPANGAN ────────────────────────────────────────────────────────
$r = $koneksi->query("SELECT COUNT(*) as total, SUM(status='tersedia') as aktif FROM lapangan");
$lap_stat = $r->fetch_assoc();
$fields_total  = $lap_stat['total'];
$fields_active = $lap_stat['aktif'];
$occupancy = $fields_total > 0 ? round($fields_active / $fields_total * 100) : 0;

// ── STAT: PENDING KONFIRMASI ──────────────────────────────────────────────
$r = $koneksi->query("SELECT COUNT(*) as c FROM pembayaran WHERE status = 'pending'");
$pending = $r->fetch_assoc()['c'];

// ── STATUS LAPANGAN (REAL-TIME) ───────────────────────────────────────────
$now_time = date('H:i:s');
$courts_result = $koneksi->query("
    SELECT l.*,
        (SELECT b.jam_selesai FROM booking b
         WHERE b.lapangan_id = l.id AND b.tanggal = '$today'
         AND b.jam_mulai <= '$now_time' AND b.jam_selesai > '$now_time'
         AND b.status IN ('dikonfirmasi','pending')
         LIMIT 1) as occupied_until,
        (SELECT u.nama FROM booking b JOIN users u ON b.user_id = u.id
         WHERE b.lapangan_id = l.id AND b.tanggal = '$today'
         AND b.jam_mulai <= '$now_time' AND b.jam_selesai > '$now_time'
         AND b.status IN ('dikonfirmasi','pending')
         LIMIT 1) as occupied_by,
        (SELECT b.jam_mulai FROM booking b
         WHERE b.lapangan_id = l.id AND b.tanggal = '$today'
         AND b.jam_mulai > '$now_time'
         AND b.status IN ('dikonfirmasi','pending')
         ORDER BY b.jam_mulai ASC LIMIT 1) as next_booking
    FROM lapangan l ORDER BY l.id ASC
");
$courts = [];
while ($row = $courts_result->fetch_assoc()) $courts[] = $row;

// ── UPCOMING BOOKING ──────────────────────────────────────────────────────
$upcoming_result = $koneksi->query("
    SELECT b.*, l.nama as nama_lapangan, u.nama as nama_user, l.harga_per_jam
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    JOIN users u ON b.user_id = u.id
    WHERE b.tanggal = '$today'
    AND b.jam_mulai >= '$now_time'
    AND b.status IN ('dikonfirmasi','pending')
    ORDER BY b.jam_mulai ASC
    LIMIT 5
");
$upcomings = [];
while ($row = $upcoming_result->fetch_assoc()) $upcomings[] = $row;

// ── PENDAPATAN BULAN INI ──────────────────────────────────────────────────
$r = $koneksi->query("
    SELECT COALESCE(SUM(p.jumlah),0) as total
    FROM pembayaran p JOIN booking b ON p.booking_id = b.id
    WHERE p.status = 'berhasil' AND b.tanggal BETWEEN '$bln_awal' AND '$bln_akhir'
");
$revenue_month = $r->fetch_assoc()['total'];

$r = $koneksi->query("SELECT COUNT(*) as c, status FROM booking WHERE tanggal BETWEEN '$bln_awal' AND '$bln_akhir' GROUP BY status");
$bln_status = ['selesai'=>0,'dikonfirmasi'=>0,'pending'=>0,'dibatalkan'=>0];
while ($row = $r->fetch_assoc()) $bln_status[$row['status']] = $row['c'];
$bln_sukses = $bln_status['selesai'] + $bln_status['dikonfirmasi'];

$target = 100000000; // Rp 100jt — sesuaikan
$pct_target = $target > 0 ? min(100, round($revenue_month / $target * 100, 1)) : 0;

// Perbandingan bulan lalu
$last_m_awal  = date('Y-m-01', strtotime('-1 month'));
$last_m_akhir = date('Y-m-t', strtotime('-1 month'));
$r = $koneksi->query("
    SELECT COALESCE(SUM(p.jumlah),0) as total
    FROM pembayaran p JOIN booking b ON p.booking_id = b.id
    WHERE p.status = 'berhasil' AND b.tanggal BETWEEN '$last_m_awal' AND '$last_m_akhir'
");
$revenue_last_month = $r->fetch_assoc()['total'];
$rev_month_change = $revenue_last_month > 0 ? round(($revenue_month - $revenue_last_month) / $revenue_last_month * 100, 1) : 0;

// ── BAR CHART BOOKING MINGGUAN ────────────────────────────────────────────
$bar_data = [];
$day_names = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dow = intval(date('w', strtotime($d)));
    $r = $koneksi->query("SELECT COUNT(*) as c FROM booking WHERE tanggal = '$d' AND status != 'dibatalkan'");
    $cnt = $r->fetch_assoc()['c'];
    $bar_data[] = ['day' => $day_names[$dow], 'count' => $cnt, 'date' => $d];
}
$bar_max = max(array_column($bar_data, 'count') ?: [1]);

// ── ICON & WARNA LAPANGAN ─────────────────────────────────────────────────
$court_icons  = ['sintetis'=>'⚽','vinyl'=>'🏟️','rumput'=>'🌿'];
$court_colors = ['sintetis'=>'from-neutral-900 to-green-950','vinyl'=>'from-neutral-900 to-red-950','rumput'=>'from-neutral-900 to-green-950'];

function courtRealStatus($c, $now_time) {
    if ($c['status'] === 'maintenance') return 'maintenance';
    if ($c['status'] === 'tidak_tersedia') return 'maintenance';
    if ($c['occupied_until']) return 'occupied';
    return 'available';
}
function courtBadge($status) {
    return match($status) {
        'occupied'    => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30">TERISI</span>',
        'available'   => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-500/15 text-green-400 border border-green-500/30">TERSEDIA</span>',
        'maintenance' => '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-500/15 text-yellow-400 border border-yellow-500/30">MAINTENANCE</span>',
        default       => '',
    };
}
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
    .card-hover { transition:transform .2s, border-color .2s; }
    .card-hover:hover { transform:translateY(-2px); border-color:rgba(232,25,44,0.3); }
    .stat-top-bar { height:2px; background:linear-gradient(90deg,#e8192c,transparent); opacity:0; transition:.2s; }
    .card-hover:hover .stat-top-bar { opacity:1; }
    .progress-fill { background:linear-gradient(90deg,#e8192c,#ff3344); }
    .modal-backdrop { backdrop-filter:blur(4px); }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .modal-box { animation:fadeUp .25s ease; }
    input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1); opacity:1; cursor:pointer; }
  </style>
</head>
<body class="font-sans flex min-h-screen">

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
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
      ['href'=>'admin_dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null,   'active'=>true],
      ['href'=>'admin_jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null,   'active'=>false],
      ['href'=>'admin_booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>$pending,'active'=>false],
      ['href'=>'admin_lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null,   'active'=>false],
      ['href'=>'admin_pembayaran.php',     'icon'=>'💳','label'=>'Pembayaran',      'badge'=>$pending,'active'=>false],
      ['href'=>'admin_laporan.php',        'icon'=>'📈','label'=>'Laporan',         'badge'=>null,   'active'=>false],
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

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<div class="flex-1 flex flex-col" style="margin-left:240px">

  <!-- TOPBAR -->
  <header class="sticky top-0 z-40 h-16 flex items-center justify-between px-7 border-b border-white/5" style="background:#141414">
    <div>
      <h1 class="font-display text-xl tracking-widest">Dashboard</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 rounded-lg px-3 py-2 border border-white/5 text-sm text-gray-500" style="background:#1a1a1a; width:220px">
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

    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <!-- Booking Hari Ini -->
      <div class="card-hover rounded-xl border border-white/5 p-5 relative overflow-hidden cursor-default" style="background:#1c1c1c">
        <div class="stat-top-bar absolute top-0 left-0 right-0"></div>
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-red-500/10 flex items-center justify-center text-lg">📅</div>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $booking_change >= 0 ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400' ?>">
            <?= $booking_change_str ?>
          </span>
        </div>
        <div class="font-display text-4xl tracking-wide leading-none"><?= $booking_today ?></div>
        <div class="text-xs text-gray-500 mt-1">Total Booking Hari Ini</div>
        <div class="text-xs text-gray-600 mt-1.5">vs minggu lalu: <?= $booking_last ?> booking</div>
      </div>

      <!-- Pendapatan -->
      <div class="card-hover rounded-xl border border-white/5 p-5 relative overflow-hidden cursor-default" style="background:#1c1c1c">
        <div class="stat-top-bar absolute top-0 left-0 right-0"></div>
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-lg">💰</div>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $rev_change >= 0 ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400' ?>">
            <?= $rev_change_str ?>
          </span>
        </div>
        <div class="font-display text-2xl tracking-wide leading-none"><?= rupiahShort($revenue_today) ?></div>
        <div class="text-xs text-gray-500 mt-1">Pendapatan Hari Ini</div>
        <div class="text-xs text-gray-600 mt-1.5">Proyeksi: <?= rupiahShort($revenue_proj) ?></div>
      </div>

      <!-- Lapangan Aktif -->
      <div class="card-hover rounded-xl border border-white/5 p-5 relative overflow-hidden cursor-default" style="background:#1c1c1c">
        <div class="stat-top-bar absolute top-0 left-0 right-0"></div>
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center text-lg">🏟️</div>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-500/15 text-green-400"><?= $occupancy ?>%</span>
        </div>
        <div class="font-display text-4xl tracking-wide leading-none"><?= $fields_active ?>/<?= $fields_total ?></div>
        <div class="text-xs text-gray-500 mt-1">Lapangan Aktif</div>
        <div class="text-xs text-green-400 mt-1.5"><?= $occupancy ?>% Tersedia</div>
      </div>

      <!-- Pending -->
      <div class="card-hover rounded-xl border border-white/5 p-5 relative overflow-hidden cursor-default" style="background:#1c1c1c">
        <div class="stat-top-bar absolute top-0 left-0 right-0"></div>
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-yellow-500/10 flex items-center justify-center text-lg">⏳</div>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-500/15 text-yellow-400"><?= $pending ?> pending</span>
        </div>
        <div class="font-display text-4xl tracking-wide leading-none"><?= $pending ?></div>
        <div class="text-xs text-gray-500 mt-1">Menunggu Konfirmasi</div>
        <div class="text-xs text-yellow-400 mt-1.5">
          <?= $pending > 0 ? '<a href="pembayaran.php" class="hover:underline">Perlu tindakan segera →</a>' : 'Semua sudah dikonfirmasi' ?>
        </div>
      </div>
    </div>

    <!-- COURT STATUS + REMINDER -->
    <div class="grid gap-4 mb-6" style="grid-template-columns:2fr 1fr">

      <!-- Court Status -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="flex items-center justify-between px-5 pt-5 pb-4">
          <div>
            <div class="text-sm font-semibold">Status Lapangan Real-time</div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $today_str ?></div>
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
          <?php foreach ($courts as $court):
            $realStatus = courtRealStatus($court, $now_time);
            $icon  = $court_icons[$court['jenis']] ?? '🏟️';
            $color = $court_colors[$court['jenis']] ?? 'from-neutral-900 to-red-950';
            $type  = (strpos(strtolower($court['jenis']), 'vinyl') !== false || strpos(strtolower($court['fasilitas'] ?? ''), 'indoor') !== false) ? 'indoor' : 'outdoor';
          ?>
          <div class="court-card-item rounded-xl border border-white/5 overflow-hidden cursor-pointer hover:border-white/15 transition"
               style="background:#1a1a1a" data-type="<?= $type ?>">
            <div class="h-20 flex items-center justify-center text-3xl bg-gradient-to-br <?= $color ?>"><?= $icon ?></div>
            <div class="p-3">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-semibold"><?= htmlspecialchars($court['nama']) ?></span>
                <?= courtBadge($realStatus) ?>
              </div>
              <div class="text-xs text-gray-500">
                <?php if ($realStatus === 'occupied'): ?>
                  <?= htmlspecialchars($court['occupied_by'] ?? '-') ?>
                <?php elseif ($realStatus === 'maintenance'): ?>
                  Sedang maintenance
                <?php else: ?>
                  Siap untuk booking
                <?php endif; ?>
              </div>
              <div class="text-xs mt-1.5">
                <?php if ($realStatus === 'available'): ?>
                  <span class="text-green-400">✓ Siap sekarang</span>
                <?php elseif ($realStatus === 'maintenance'): ?>
                  <span class="text-yellow-400">🔧 Tidak tersedia</span>
                <?php else: ?>
                  <span class="text-gray-500">⏱ Selesai <?= substr($court['occupied_until'],0,5) ?></span>
                <?php endif; ?>
                <?php if ($court['next_booking'] && $realStatus === 'available'): ?>
                  <span class="text-gray-600 ml-1">· Next: <?= substr($court['next_booking'],0,5) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($courts)): ?>
          <div class="col-span-2 text-center text-gray-600 text-sm py-8">Belum ada data lapangan.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Reminder -->
      <div class="rounded-xl border border-white/5 overflow-hidden flex flex-col" style="background:#1c1c1c">
        <div class="px-5 pt-5 pb-3">
          <div class="text-sm font-semibold">Informasi</div>
          <div class="text-xs text-gray-500 mt-0.5">Catatan owner</div>
        </div>
        <div class="mx-5 mb-4 rounded-xl p-3.5 border border-red-500/20"
             style="background:linear-gradient(135deg,rgba(232,25,44,0.1),rgba(232,25,44,0.04))">
          <div class="text-xs font-bold text-brand-red tracking-widest uppercase mb-1">Reminder</div>
          <div class="text-xs text-gray-300 leading-relaxed">Sabtu dan Minggu biasanya terdapat lonjakan customer. Pastikan lapangan indoor sudah dibersihkan dan follow up booking yang masih pending!</div>
        </div>
        <?php if ($pending > 0): ?>
        <div class="mx-5 mb-5 rounded-xl p-3.5 border border-yellow-500/20" style="background:rgba(245,158,11,0.05)">
          <div class="text-xs font-bold text-yellow-400 tracking-widest uppercase mb-1">⚠ Perhatian</div>
          <div class="text-xs text-gray-300">Ada <span class="text-yellow-400 font-bold"><?= $pending ?> pembayaran</span> menunggu konfirmasi.</div>
          <a href="pembayaran.php" class="inline-block mt-2 text-xs text-yellow-400 hover:underline">Konfirmasi sekarang →</a>
        </div>
        <?php endif; ?>

        <!-- Mini chart booking 7 hari -->
        <div class="px-5 pb-5 flex-1">
          <div class="text-xs text-gray-500 mb-2">Booking 7 hari terakhir</div>
          <div class="flex items-end gap-1 h-16">
            <?php foreach ($bar_data as $bd):
              $pct = $bar_max > 0 ? round($bd['count']/$bar_max*100) : 0;
              $h   = max(2, intval($pct*64/100));
              $isToday = $bd['date'] === $today;
            ?>
            <div class="flex-1 flex flex-col items-center gap-1 group relative">
              <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap z-10">
                <?= $bd['day'] ?>: <?= $bd['count'] ?>
              </div>
              <div class="w-full rounded-sm" style="height:<?= $h ?>px;background:<?= $isToday ? '#e8192c' : ($bd['count']>0?'rgba(232,25,44,0.4)':'rgba(255,255,255,0.05)') ?>"></div>
              <span class="text-xs text-gray-600"><?= $bd['day'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
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
            <div class="text-xs text-gray-500 mt-0.5">Hari ini — <?= date('d M Y') ?></div>
          </div>
          <a href="admin_booking.php" class="text-xs font-semibold text-brand-red hover:text-brand-red2 transition no-underline">Lihat Semua →</a>
        </div>
        <div class="px-5 pb-5">
          <?php if (empty($upcomings)): ?>
          <div class="text-center text-gray-600 text-sm py-6">Tidak ada booking tersisa hari ini.</div>
          <?php else: ?>
          <?php foreach ($upcomings as $i => $bk): ?>
          <div class="flex items-center gap-3 py-3 <?= $i < count($upcomings)-1 ? 'border-b border-white/[0.04]' : '' ?>
                      cursor-pointer rounded-lg hover:bg-white/[0.03] transition -mx-2 px-2">
            <div class="min-w-[56px] text-center rounded-lg py-1.5" style="background:#1a1a1a">
              <div class="font-display text-lg leading-none"><?= substr($bk['jam_mulai'],0,5) ?></div>
              <div class="text-xs text-gray-500">WIB</div>
            </div>
            <div class="flex-1">
              <div class="text-sm font-semibold"><?= htmlspecialchars($bk['nama_user']) ?></div>
              <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($bk['nama_lapangan']) ?> · <?= $bk['durasi_jam'] ?> jam</div>
            </div>
            <div>
              <div class="text-sm font-semibold text-green-400"><?= rupiahShort($bk['total_harga']) ?></div>
              <?php if ($bk['status'] === 'pending'): ?>
              <div class="text-xs text-yellow-400 text-right">pending</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Revenue Bulan Ini -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <div>
            <div class="text-sm font-semibold">Pendapatan Bulan Ini</div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $months_id[$bulan ?? intval(date('n'))] ?? date('F') ?> <?= date('Y') ?></div>
          </div>
          <a href="laporan.php" class="text-xs font-semibold text-brand-red hover:text-brand-red2 transition no-underline">Detail →</a>
        </div>
        <div class="flex items-end justify-between px-5 pb-3">
          <div>
            <div class="font-display text-3xl tracking-wide"><?= rupiahShort($revenue_month) ?></div>
            <div class="text-xs text-gray-500 mt-1">Target: <?= rupiahShort($target) ?></div>
          </div>
          <div class="text-right">
            <div class="text-sm font-semibold <?= $rev_month_change >= 0 ? 'text-green-400' : 'text-red-400' ?>">
              <?= ($rev_month_change >= 0 ? '↑' : '↓') ?> <?= abs($rev_month_change) ?>%
            </div>
            <div class="text-xs text-gray-500">vs bulan lalu</div>
          </div>
        </div>
        <div class="px-5 pb-4">
          <div class="flex justify-between text-xs mb-1.5">
            <span class="text-gray-500">Progress Target</span>
            <span class="font-semibold text-white"><?= $pct_target ?>%</span>
          </div>
          <div class="h-1.5 rounded-full overflow-hidden" style="background:#1a1a1a">
            <div class="progress-fill h-full rounded-full" style="width:<?= $pct_target ?>%"></div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2.5 px-5 pb-5">
          <?php foreach([
            ['val'=>$bln_sukses,                  'lbl'=>'Sukses',     'clr'=>'text-white'],
            ['val'=>$bln_status['pending'],        'lbl'=>'Pending',    'clr'=>'text-yellow-400'],
            ['val'=>$bln_status['dibatalkan'],     'lbl'=>'Dibatalkan', 'clr'=>'text-red-400'],
          ] as $m): ?>
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

<!-- MODAL BOOKING CEPAT -->
<div id="modal" class="modal-backdrop fixed inset-0 bg-black/70 z-50 hidden items-center justify-center"
     onclick="if(event.target===this)closeModal()">
  <div class="modal-box rounded-2xl p-7 w-[480px] max-w-[95vw] border border-white/5" style="background:#1c1c1c">
    <div class="font-display text-2xl tracking-widest mb-1">Tambah Booking</div>
    <div class="text-xs text-gray-500 mb-5">Buat reservasi lapangan baru</div>
    <form method="POST" action="admin_booking.php">
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">Nama Pelanggan</label>
        <input type="text" name="nama_user" placeholder="Nama lengkap"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">No. WhatsApp</label>
        <input type="text" name="no_wa" placeholder="08xxxxxxxxxx"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
      <div class="flex flex-col gap-1.5 col-span-2">
        <label class="text-xs text-gray-500">Pilih Lapangan</label>
        <select name="lapangan_id" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <?php foreach ($courts as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama']) ?> (Rp <?= number_format($c['harga_per_jam'],0,',','.') ?>/jam)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">Tanggal</label>
        <input type="date" name="tanggal" value="<?= $today ?>"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition"
               style="background:#1a1a1a; color-scheme:dark" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">Jam Mulai</label>
        <select name="jam_mulai" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <?php foreach(['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'] as $h): ?>
          <option><?= $h ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">Durasi</label>
        <select name="durasi" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
          <option value="1">1 Jam</option>
          <option value="2" selected>2 Jam</option>
          <option value="3">3 Jam</option>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500">Catatan</label>
        <input type="text" name="catatan" placeholder="Opsional"
               class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
      </div>
    </div>
    <div class="flex gap-2.5 mt-5">
      <button type="button" onclick="closeModal()" class="flex-1 py-2.5 rounded-lg border border-white/10 text-gray-400 text-sm hover:bg-white/5 transition">Batal</button>
      <a href="admin_booking.php" class="flex-[2] py-2.5 rounded-lg bg-brand-red text-white text-sm font-semibold hover:bg-brand-red2 transition text-center no-underline">
        ✓ Ke Halaman Booking
      </a>
    </div>
    </form>
  </div>
</div>

<script>
function openModal()  { const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
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