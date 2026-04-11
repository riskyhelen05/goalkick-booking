<?php
session_start();
include '../koneksi.php';

// ── FILTER PERIODE ───────────────────────────────────────────────────────
$bulan  = isset($_GET['bulan'])  ? intval($_GET['bulan'])  : intval(date('n'));
$tahun  = isset($_GET['tahun'])  ? intval($_GET['tahun'])  : intval(date('Y'));
$bulan  = max(1, min(12, $bulan));
$tahun  = max(2020, min(2030, $tahun));

$awal  = "$tahun-" . str_pad($bulan,2,'0',STR_PAD_LEFT) . "-01";
$akhir = date('Y-m-t', strtotime($awal));

// ── HELPER ───────────────────────────────────────────────────────────────
function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');

// ── RINGKASAN UTAMA ───────────────────────────────────────────────────────
$rSummary = $koneksi->query("
  SELECT 
    COUNT(b.id) as total_booking,
    SUM(CASE WHEN b.status='selesai' OR b.status='dikonfirmasi' THEN 1 ELSE 0 END) as booking_sukses,
    SUM(CASE WHEN b.status='dibatalkan' THEN 1 ELSE 0 END) as booking_batal,
    SUM(CASE WHEN b.status='pending' THEN 1 ELSE 0 END) as booking_pending
  FROM booking b
  WHERE b.tanggal BETWEEN '$awal' AND '$akhir'
");
$summary = $rSummary->fetch_assoc();

$rPendapatan = $koneksi->query("
  SELECT COALESCE(SUM(p.jumlah),0) as total_pendapatan
  FROM pembayaran p
  JOIN booking b ON p.booking_id = b.id
  WHERE p.status = 'berhasil'
  AND b.tanggal BETWEEN '$awal' AND '$akhir'
");
$pendapatan = $rPendapatan->fetch_assoc()['total_pendapatan'];

// ── PER LAPANGAN ──────────────────────────────────────────────────────────
$rLap = $koneksi->query("
  SELECT l.nama, l.jenis,
    COUNT(b.id) as total_booking,
    COALESCE(SUM(CASE WHEN b.status IN ('selesai','dikonfirmasi') THEN b.total_harga ELSE 0 END),0) as pendapatan
  FROM lapangan l
  LEFT JOIN booking b ON l.id = b.lapangan_id AND b.tanggal BETWEEN '$awal' AND '$akhir'
  GROUP BY l.id, l.nama, l.jenis
  ORDER BY pendapatan DESC
");
$lapRows = [];
while ($r = $rLap->fetch_assoc()) $lapRows[] = $r;

// ── PER METODE PEMBAYARAN ────────────────────────────────────────────────
$rMetode = $koneksi->query("
  SELECT p.metode,
    COUNT(p.id) as jumlah_transaksi,
    COALESCE(SUM(p.jumlah),0) as total
  FROM pembayaran p
  JOIN booking b ON p.booking_id = b.id
  WHERE p.status = 'berhasil'
  AND b.tanggal BETWEEN '$awal' AND '$akhir'
  GROUP BY p.metode
  ORDER BY total DESC
");
$metodeRows = [];
while ($r = $rMetode->fetch_assoc()) $metodeRows[] = $r;

// ── BOOKING HARIAN (untuk chart sederhana) ───────────────────────────────
$rHarian = $koneksi->query("
  SELECT DAY(tanggal) as hari, COUNT(*) as jumlah
  FROM booking
  WHERE tanggal BETWEEN '$awal' AND '$akhir'
  AND status IN ('selesai','dikonfirmasi','pending')
  GROUP BY DAY(tanggal)
  ORDER BY hari ASC
");
$harianData = [];
while ($r = $rHarian->fetch_assoc()) $harianData[intval($r['hari'])] = intval($r['jumlah']);
$maxHarian = $harianData ? max($harianData) : 1;
$daysInMonth = intval(date('t', strtotime($awal)));

// ── TOP PELANGGAN ────────────────────────────────────────────────────────
$rTop = $koneksi->query("
  SELECT u.nama, u.email,
    COUNT(b.id) as total_booking,
    COALESCE(SUM(CASE WHEN b.status IN ('selesai','dikonfirmasi') THEN b.total_harga ELSE 0 END),0) as total_spend
  FROM users u
  JOIN booking b ON u.id = b.user_id
  WHERE b.tanggal BETWEEN '$awal' AND '$akhir'
  GROUP BY u.id, u.nama, u.email
  ORDER BY total_spend DESC
  LIMIT 5
");
$topRows = [];
while ($r = $rTop->fetch_assoc()) $topRows[] = $r;

// ── TRANSAKSI TERBARU ──────────────────────────────────────────────────────
$rTrx = $koneksi->query("
  SELECT p.*, b.kode_booking, b.tanggal, l.nama as nama_lapangan, u.nama as nama_user
  FROM pembayaran p
  JOIN booking b ON p.booking_id = b.id
  JOIN lapangan l ON b.lapangan_id = l.id
  JOIN users u ON b.user_id = u.id
  WHERE b.tanggal BETWEEN '$awal' AND '$akhir'
  ORDER BY p.id DESC
  LIMIT 10
");
$trxRows = [];
while ($r = $rTrx->fetch_assoc()) $trxRows[] = $r;

// ── BULAN-TAHUN PREV/NEXT ─────────────────────────────────────────────────
$prevDate = date('Y-n', strtotime("$awal -1 month"));
$nextDate = date('Y-n', strtotime("$awal +1 month"));
[$prevTahun, $prevBulan] = explode('-', $prevDate);
[$nextTahun, $nextBulan] = explode('-', $nextDate);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartFutsal — Laporan</title>
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
    .badge-pending  { background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); }
    .badge-berhasil { background:rgba(34,197,94,0.15);  color:#22c55e; border:1px solid rgba(34,197,94,0.3); }
    .badge-gagal    { background:rgba(239,68,68,0.15);  color:#ef4444; border:1px solid rgba(239,68,68,0.3); }
    .bar { transition: height .4s ease; }
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
      ['href'=>'admin_dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null, 'active'=>false],
      ['href'=>'admin_jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null, 'active'=>false],
      ['href'=>'admin_booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>3,    'active'=>false],
      ['href'=>'admin_lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null, 'active'=>false],
      ['href'=>'pembayaran.php',     'icon'=>'💳','label'=>'Pembayaran',      'badge'=>null, 'active'=>false],
      ['href'=>'laporan.php',        'icon'=>'📈','label'=>'Laporan',         'badge'=>null, 'active'=>true],
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
      <h1 class="font-display text-xl tracking-widest">Laporan Bisnis</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <!-- PERIOD NAVIGATOR -->
    <div class="flex items-center gap-2">
      <a href="?bulan=<?= $prevBulan ?>&tahun=<?= $prevTahun ?>"
         class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-gray-400 hover:bg-white/5 transition no-underline">‹</a>
      <form method="GET" class="flex items-center gap-2">
        <select name="bulan" class="bg-[#1a1a1a] border border-white/10 text-white text-xs px-2 py-1.5 rounded-lg outline-none">
          <?php for ($m=1;$m<=12;$m++): ?>
          <option value="<?= $m ?>" <?= $m===$bulan?'selected':'' ?>><?= $months_id[$m] ?></option>
          <?php endfor; ?>
        </select>
        <select name="tahun" class="bg-[#1a1a1a] border border-white/10 text-white text-xs px-2 py-1.5 rounded-lg outline-none">
          <?php for ($y=2024;$y<=2027;$y++): ?>
          <option value="<?= $y ?>" <?= $y===$tahun?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-brand-red text-white hover:bg-brand-red2 transition">Tampilkan</button>
      </form>
      <a href="?bulan=<?= $nextBulan ?>&tahun=<?= $nextTahun ?>"
         class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-gray-400 hover:bg-white/5 transition no-underline">›</a>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="p-7 flex-1">

    <p class="text-sm text-gray-500 mb-5">Periode: <span class="text-white font-semibold"><?= $months_id[$bulan] . ' ' . $tahun ?></span></p>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <?php
      $cards = [
        ['label'=>'Total Pendapatan', 'val'=>rupiah($pendapatan),          'icon'=>'💰','color'=>'#22c55e', 'sub'=>'Sudah dikonfirmasi'],
        ['label'=>'Total Booking',    'val'=>$summary['total_booking'],     'icon'=>'📋','color'=>'#3b82f6', 'sub'=>'Semua status'],
        ['label'=>'Booking Sukses',   'val'=>$summary['booking_sukses'],    'icon'=>'✅','color'=>'#22c55e', 'sub'=>'Selesai & dikonfirmasi'],
        ['label'=>'Dibatalkan',       'val'=>$summary['booking_batal'],     'icon'=>'❌','color'=>'#ef4444', 'sub'=>'Bulan ini'],
      ];
      foreach ($cards as $c): ?>
      <div class="rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
        <div class="flex items-start justify-between mb-3">
          <span class="text-xs text-gray-500"><?= $c['label'] ?></span>
          <span class="text-lg"><?= $c['icon'] ?></span>
        </div>
        <div class="text-xl font-bold" style="color:<?= $c['color'] ?>"><?= $c['val'] ?></div>
        <div class="text-xs text-gray-600 mt-1"><?= $c['sub'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- CHART + PER LAPANGAN -->
    <div class="grid grid-cols-3 gap-4 mb-6">

      <!-- GRAFIK HARIAN -->
      <div class="col-span-2 rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
        <div class="flex items-center justify-between mb-4">
          <div class="text-sm font-semibold text-white">📊 Booking Harian</div>
          <div class="text-xs text-gray-500"><?= $months_id[$bulan] ?> <?= $tahun ?></div>
        </div>
        <div class="flex items-end gap-1 h-28">
          <?php for ($d=1; $d<=$daysInMonth; $d++):
            $val = $harianData[$d] ?? 0;
            $pct = $maxHarian > 0 ? round(($val/$maxHarian)*100) : 0;
            $h   = max(2, intval($pct * 112 / 100));
          ?>
          <div class="flex-1 flex flex-col items-center gap-1 group relative">
            <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap z-10">
              Tgl <?= $d ?>: <?= $val ?> booking
            </div>
            <div class="bar w-full rounded-sm"
                 style="height:<?= $h ?>px; background:<?= $val>0?'rgba(232,25,44,0.7)':'rgba(255,255,255,0.05)' ?>"></div>
          </div>
          <?php endfor; ?>
        </div>
        <div class="flex justify-between mt-1">
          <span class="text-xs text-gray-600">1</span>
          <span class="text-xs text-gray-600"><?= ceil($daysInMonth/2) ?></span>
          <span class="text-xs text-gray-600"><?= $daysInMonth ?></span>
        </div>
      </div>

      <!-- PER METODE BAYAR -->
      <div class="rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
        <div class="text-sm font-semibold text-white mb-4">💳 Per Metode Bayar</div>
        <?php if (empty($metodeRows)): ?>
        <p class="text-xs text-gray-600">Belum ada transaksi.</p>
        <?php else: ?>
        <?php
        $totalMetode = array_sum(array_column($metodeRows,'total'));
        $metodeIcons = ['transfer'=>'🏦','cash'=>'💵','e-wallet'=>'📱'];
        foreach ($metodeRows as $m):
          $pct = $totalMetode > 0 ? round($m['total']/$totalMetode*100) : 0;
        ?>
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs flex items-center gap-1">
              <span><?= $metodeIcons[$m['metode']] ?? '💳' ?></span>
              <span class="capitalize text-gray-300"><?= $m['metode'] ?></span>
            </span>
            <span class="text-xs text-gray-500"><?= $pct ?>%</span>
          </div>
          <div class="w-full rounded-full h-1.5" style="background:#2a2a2a">
            <div class="h-1.5 rounded-full" style="width:<?= $pct ?>%;background:#e8192c"></div>
          </div>
          <div class="text-xs text-gray-500 mt-0.5"><?= rupiah($m['total']) ?> · <?= $m['jumlah_transaksi'] ?> trx</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- PER LAPANGAN + TOP PELANGGAN -->
    <div class="grid grid-cols-2 gap-4 mb-6">

      <!-- PER LAPANGAN -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="px-5 py-4 border-b border-white/5 text-sm font-semibold text-white">🏟️ Performa Per Lapangan</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-600 uppercase border-b border-white/5">
              <th class="px-5 py-2.5 text-left">Lapangan</th>
              <th class="px-5 py-2.5 text-right">Booking</th>
              <th class="px-5 py-2.5 text-right">Pendapatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lapRows)): ?>
            <tr><td colspan="3" class="px-5 py-6 text-center text-gray-600 text-xs">Tidak ada data.</td></tr>
            <?php else: ?>
            <?php foreach ($lapRows as $lap): ?>
            <tr class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
              <td class="px-5 py-3">
                <div class="font-semibold text-white text-xs"><?= htmlspecialchars($lap['nama']) ?></div>
                <div class="text-xs text-gray-600 capitalize"><?= $lap['jenis'] ?></div>
              </td>
              <td class="px-5 py-3 text-right text-gray-300 text-xs"><?= $lap['total_booking'] ?></td>
              <td class="px-5 py-3 text-right text-xs font-semibold text-green-400"><?= rupiah($lap['pendapatan']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- TOP PELANGGAN -->
      <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
        <div class="px-5 py-4 border-b border-white/5 text-sm font-semibold text-white">👑 Top Pelanggan</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-600 uppercase border-b border-white/5">
              <th class="px-5 py-2.5 text-left">#</th>
              <th class="px-5 py-2.5 text-left">Pelanggan</th>
              <th class="px-5 py-2.5 text-right">Booking</th>
              <th class="px-5 py-2.5 text-right">Total Spend</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topRows)): ?>
            <tr><td colspan="4" class="px-5 py-6 text-center text-gray-600 text-xs">Tidak ada data.</td></tr>
            <?php else: ?>
            <?php foreach ($topRows as $i=>$t): ?>
            <tr class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
              <td class="px-5 py-3 text-gray-600 text-xs font-bold"><?= $i+1 ?></td>
              <td class="px-5 py-3">
                <div class="font-semibold text-white text-xs"><?= htmlspecialchars($t['nama']) ?></div>
                <div class="text-xs text-gray-600"><?= htmlspecialchars($t['email']) ?></div>
              </td>
              <td class="px-5 py-3 text-right text-gray-300 text-xs"><?= $t['total_booking'] ?></td>
              <td class="px-5 py-3 text-right text-xs font-semibold text-yellow-400"><?= rupiah($t['total_spend']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TRANSAKSI TERBARU -->
    <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
      <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
        <div class="text-sm font-semibold text-white">🧾 Riwayat Transaksi</div>
        <a href="pembayaran.php?status=semua" class="text-xs text-brand-red hover:underline no-underline">Lihat semua →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-600 uppercase border-b border-white/5">
              <th class="px-5 py-2.5 text-left">Kode</th>
              <th class="px-5 py-2.5 text-left">User</th>
              <th class="px-5 py-2.5 text-left">Lapangan</th>
              <th class="px-5 py-2.5 text-left">Tanggal</th>
              <th class="px-5 py-2.5 text-left">Metode</th>
              <th class="px-5 py-2.5 text-right">Jumlah</th>
              <th class="px-5 py-2.5 text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($trxRows)): ?>
            <tr><td colspan="7" class="px-5 py-8 text-center text-gray-600 text-sm">Tidak ada transaksi bulan ini.</td></tr>
            <?php else: ?>
            <?php foreach ($trxRows as $t): ?>
            <tr class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
              <td class="px-5 py-3 font-mono text-xs text-brand-red"><?= htmlspecialchars($t['kode_booking']) ?></td>
              <td class="px-5 py-3 text-xs text-white"><?= htmlspecialchars($t['nama_user']) ?></td>
              <td class="px-5 py-3 text-xs text-gray-400"><?= htmlspecialchars($t['nama_lapangan']) ?></td>
              <td class="px-5 py-3 text-xs text-gray-400"><?= date('d M Y', strtotime($t['tanggal'])) ?></td>
              <td class="px-5 py-3 text-xs capitalize text-gray-400"><?= $t['metode'] ?></td>
              <td class="px-5 py-3 text-xs text-right font-semibold text-white"><?= rupiah($t['jumlah']) ?></td>
              <td class="px-5 py-3">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full badge-<?= $t['status'] ?>">
                  <?= ucfirst($t['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

</body>
</html>