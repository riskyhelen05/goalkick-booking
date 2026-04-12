<?php
session_start();
include '../koneksi.php';

// ── HANDLE KONFIRMASI / TOLAK PEMBAYARAN ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = intval($_POST['pembayaran_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['konfirmasi', 'tolak'])) {
        if ($action === 'konfirmasi') {
            $status_bayar  = 'berhasil';
            $status_booking = 'dikonfirmasi';
            $paid_at = date('Y-m-d H:i:s');
            $koneksi->query("UPDATE pembayaran SET status='berhasil', paid_at='$paid_at' WHERE id=$id");
            // Update status booking juga
            $res = $koneksi->query("SELECT booking_id FROM pembayaran WHERE id=$id");
            if ($row = $res->fetch_assoc()) {
                $bid = intval($row['booking_id']);
                $koneksi->query("UPDATE booking SET status='dikonfirmasi' WHERE id=$bid");
                // Insert notifikasi
                $resB = $koneksi->query("SELECT user_id FROM booking WHERE id=$bid");
                if ($rowB = $resB->fetch_assoc()) {
                    $uid = intval($rowB['user_id']);
                    $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe) VALUES ($uid, $bid, 'Pembayaran Dikonfirmasi', 'Pembayaran booking kamu telah dikonfirmasi oleh admin.', 'success')");
                }
            }
        } else {
            $koneksi->query("UPDATE pembayaran SET status='gagal' WHERE id=$id");
            $res = $koneksi->query("SELECT booking_id FROM pembayaran WHERE id=$id");
            if ($row = $res->fetch_assoc()) {
                $bid = intval($row['booking_id']);
                $koneksi->query("UPDATE booking SET status='dibatalkan' WHERE id=$bid");
                $resB = $koneksi->query("SELECT user_id FROM booking WHERE id=$bid");
                if ($rowB = $resB->fetch_assoc()) {
                    $uid = intval($rowB['user_id']);
                    $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe) VALUES ($uid, $bid, 'Pembayaran Ditolak', 'Pembayaran booking kamu ditolak. Hubungi admin untuk info lebih lanjut.', 'warning')");
                }
            }
        }
        header('Location: admin_pembayaran.php?msg=' . $action);
        exit;
    }
}

// ── FILTER ───────────────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'pending';
$valid_status  = ['pending', 'berhasil', 'gagal', 'semua'];
if (!in_array($filter_status, $valid_status)) $filter_status = 'pending';

$where = $filter_status !== 'semua' ? "WHERE p.status = '$filter_status'" : '';

// ── AMBIL DATA PEMBAYARAN ─────────────────────────────────────────────────
$query = "
    SELECT p.*, 
           b.kode_booking, b.tanggal, b.jam_mulai, b.jam_selesai, b.durasi_jam, b.total_harga as total_booking,
           l.nama as nama_lapangan,
           u.nama as nama_user, u.no_whatsapp, u.email
    FROM pembayaran p
    JOIN booking b ON p.booking_id = b.id
    JOIN lapangan l ON b.lapangan_id = l.id
    JOIN users u ON b.user_id = u.id
    $where
    ORDER BY p.id DESC
";
$result = $koneksi->query($query);
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// ── COUNTS ───────────────────────────────────────────────────────────────
$counts = ['pending' => 0, 'berhasil' => 0, 'gagal' => 0, 'semua' => 0];
$rc = $koneksi->query("SELECT status, COUNT(*) as c FROM pembayaran GROUP BY status");
while ($r = $rc->fetch_assoc()) {
    $counts[$r['status']] = $r['c'];
    $counts['semua'] += $r['c'];
}

// ── TANGGAL ───────────────────────────────────────────────────────────────
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function metodeIcon($m) {
    return match($m) {
        'transfer' => '🏦',
        'cash'     => '💵',
        'e-wallet' => '📱',
        default    => '💳',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartFutsal — Pembayaran</title>
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
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .modal-box { animation:fadeUp .25s ease; }
    .badge-pending  { background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); }
    .badge-berhasil { background:rgba(34,197,94,0.15);  color:#22c55e; border:1px solid rgba(34,197,94,0.3); }
    .badge-gagal    { background:rgba(239,68,68,0.15);  color:#ef4444; border:1px solid rgba(239,68,68,0.3); }
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
      ['href'=>'admin_dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null,'active'=>false],
      ['href'=>'admin_jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null,'active'=>false],
      ['href'=>'admin_booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>3,   'active'=>false],
      ['href'=>'admin_lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null,'active'=>false],
      ['href'=>'admin_pembayaran.php',     'icon'=>'💳','label'=>'Pembayaran',      'badge'=>$counts['pending'],'active'=>true],
      ['href'=>'admin_laporan.php',        'icon'=>'📈','label'=>'Laporan',         'badge'=>null,'active'=>false],
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
      <h1 class="font-display text-xl tracking-widest">Konfirmasi Pembayaran</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 rounded-lg px-3 py-2 border border-white/5 text-sm text-gray-500"
           style="background:#1a1a1a; width:220px">
        <span>🔍</span>
        <input type="text" placeholder="Cari booking, user…" class="bg-transparent outline-none text-white text-xs w-full placeholder-gray-600" />
      </div>
      <div class="relative w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base" style="background:#1a1a1a">
        🔔<div class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-brand-red rounded-full border border-neutral-800"></div>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="p-7 flex-1">

    <?php if (isset($_GET['msg'])): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-semibold <?= $_GET['msg']==='konfirmasi' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
      <?= $_GET['msg']==='konfirmasi' ? '✅ Pembayaran berhasil dikonfirmasi.' : '❌ Pembayaran telah ditolak.' ?>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <?php
      $stats = [
        ['label'=>'Total Masuk',   'val'=>$counts['semua'],    'icon'=>'💳','color'=>'#3b82f6'],
        ['label'=>'Menunggu',      'val'=>$counts['pending'],  'icon'=>'⏳','color'=>'#f59e0b'],
        ['label'=>'Dikonfirmasi',  'val'=>$counts['berhasil'], 'icon'=>'✅','color'=>'#22c55e'],
        ['label'=>'Ditolak',       'val'=>$counts['gagal'],    'icon'=>'❌','color'=>'#ef4444'],
      ];
      foreach ($stats as $s): ?>
      <div class="rounded-xl border border-white/5 p-4" style="background:#1c1c1c">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs text-gray-500"><?= $s['label'] ?></span>
          <span class="text-lg"><?= $s['icon'] ?></span>
        </div>
        <div class="text-2xl font-bold" style="color:<?= $s['color'] ?>"><?= $s['val'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- FILTER TABS -->
    <div class="flex items-center gap-2 mb-4">
      <?php foreach (['pending'=>'⏳ Pending','berhasil'=>'✅ Dikonfirmasi','gagal'=>'❌ Ditolak','semua'=>'📋 Semua'] as $k=>$lbl): ?>
      <a href="?status=<?= $k ?>"
         class="text-xs px-4 py-1.5 rounded-lg border transition no-underline
                <?= $filter_status===$k ? 'bg-brand-red text-white border-brand-red' : 'text-gray-500 border-white/10 hover:bg-white/5' ?>">
        <?= $lbl ?>
        <span class="ml-1 opacity-60">(<?= $counts[$k] ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- TABLE -->
    <div class="rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/5 text-xs text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3 text-left">Kode Booking</th>
              <th class="px-5 py-3 text-left">Pelanggan</th>
              <th class="px-5 py-3 text-left">Lapangan</th>
              <th class="px-5 py-3 text-left">Tanggal & Jam</th>
              <th class="px-5 py-3 text-left">Metode</th>
              <th class="px-5 py-3 text-left">Jumlah</th>
              <th class="px-5 py-3 text-left">Status</th>
              <th class="px-5 py-3 text-left">Bukti</th>
              <th class="px-5 py-3 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
            <tr>
              <td colspan="9" class="px-5 py-10 text-center text-gray-600 text-sm">
                Tidak ada data pembayaran <?= $filter_status !== 'semua' ? "dengan status <b>$filter_status</b>" : '' ?>.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
              <td class="px-5 py-4">
                <div class="font-mono text-xs text-brand-red"><?= htmlspecialchars($r['kode_booking']) ?></div>
                <div class="text-xs text-gray-600 mt-0.5">#<?= $r['id'] ?></div>
              </td>
              <td class="px-5 py-4">
                <div class="font-semibold text-white"><?= htmlspecialchars($r['nama_user']) ?></div>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($r['email']) ?></div>
                <?php if ($r['no_whatsapp']): ?>
                <div class="text-xs text-green-500 mt-0.5">📱 <?= htmlspecialchars($r['no_whatsapp']) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-5 py-4">
                <div class="text-white"><?= htmlspecialchars($r['nama_lapangan']) ?></div>
                <div class="text-xs text-gray-500"><?= $r['durasi_jam'] ?> jam</div>
              </td>
              <td class="px-5 py-4">
                <div class="text-white"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                <div class="text-xs text-gray-500"><?= substr($r['jam_mulai'],0,5) ?>–<?= substr($r['jam_selesai'],0,5) ?></div>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-1.5">
                  <span><?= metodeIcon($r['metode']) ?></span>
                  <span class="capitalize text-gray-300"><?= $r['metode'] ?></span>
                </div>
              </td>
              <td class="px-5 py-4 font-bold text-white"><?= rupiah($r['jumlah']) ?></td>
              <td class="px-5 py-4">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full badge-<?= $r['status'] ?>">
                  <?= ucfirst($r['status']) ?>
                </span>
              </td>
              <td class="px-5 py-4">
                <?php if ($r['bukti_url']): ?>
<button onclick="showBukti('../uploads/bukti/<?= htmlspecialchars($r['bukti_url']) ?>')"
    class="text-xs px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500/20">
    📄 Lihat Foto
</button>
                <?php else: ?>
                <span class="text-xs text-gray-600">Tidak ada</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-4">
                <?php if ($r['status'] === 'pending'): ?>
                <div class="flex gap-2">
                  <form method="POST" onsubmit="return confirm('Konfirmasi pembayaran ini?')">
                    <input type="hidden" name="pembayaran_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="konfirmasi">
                    <button type="submit"
                            class="text-xs px-3 py-1.5 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 hover:bg-green-500/20 transition">
                      ✅ Konfirmasi
                    </button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Tolak pembayaran ini?')">
                    <input type="hidden" name="pembayaran_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="tolak">
                    <button type="submit"
                            class="text-xs px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 transition">
                      ❌ Tolak
                    </button>
                  </form>
                </div>
                <?php elseif ($r['status'] === 'berhasil'): ?>
                <span class="text-xs text-green-500">✔ Dikonfirmasi</span>
                <?php else: ?>
                <span class="text-xs text-red-400">✖ Ditolak</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- INFO REKENING -->
    <div class="mt-6 rounded-xl border border-white/5 p-5" style="background:#1c1c1c">
      <div class="text-sm font-semibold text-white mb-3">📋 Rekening & Dompet Digital yang Diterima</div>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <?php
        $rekenings = [
          ['nama'=>'BRI',  'no'=>'1234-5678-9012-3456', 'atas'=>'SmartFutsal','icon'=>'🏦','color'=>'#3b82f6'],
          ['nama'=>'BNI',  'no'=>'8765-4321-0000-1111', 'atas'=>'SmartFutsal','icon'=>'🏦','color'=>'#10b981'],
          ['nama'=>'BCA',  'no'=>'0123-4567-8900-1122', 'atas'=>'SmartFutsal','icon'=>'🏦','color'=>'#f59e0b'],
          ['nama'=>'GoPay','no'=>'0812-3456-7890',       'atas'=>'SmartFutsal','icon'=>'📱','color'=>'#22c55e'],
          ['nama'=>'OVO',  'no'=>'0812-3456-7890',       'atas'=>'SmartFutsal','icon'=>'📱','color'=>'#8b5cf6'],
        ];
        foreach ($rekenings as $rek): ?>
        <div class="rounded-lg border border-white/5 px-3 py-2.5 hover:border-white/10 transition" style="background:#141414">
          <div class="flex items-center gap-2 mb-1">
            <span><?= $rek['icon'] ?></span>
            <span class="text-xs font-bold" style="color:<?= $rek['color'] ?>"><?= $rek['nama'] ?></span>
          </div>
          <div class="text-xs text-white font-mono"><?= $rek['no'] ?></div>
          <div class="text-xs text-gray-600 mt-0.5">a.n <?= $rek['atas'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>
</div>

<!-- ══════════════════ MODAL BUKTI PEMBAYARAN ══════════════════ -->
<div id="buktiModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop" style="background:rgba(0,0,0,0.8)">
  <div class="modal-box rounded-2xl border border-white/10 p-6 relative" style="background:#1c1c1c; max-width:480px; width:90%">
    <button onclick="closeBukti()" class="absolute top-4 right-4 text-gray-500 hover:text-white text-lg transition">✕</button>
    <div class="text-sm font-semibold text-white mb-3">🖼 Bukti Pembayaran</div>
    <img id="buktiImg" src="" alt="Bukti Pembayaran"
         class="w-full rounded-xl border border-white/10 object-contain max-h-96 bg-black" />
    <p class="text-xs text-gray-500 mt-2 text-center">Verifikasi kesesuaian jumlah sebelum konfirmasi.</p>
  </div>
</div>

<script>
function showBukti(url) {
  document.getElementById('buktiImg').src = url;
  document.getElementById('buktiModal').classList.remove('hidden');
}
function closeBukti() {
  document.getElementById('buktiModal').classList.add('hidden');
  document.getElementById('buktiImg').src = '';
}
document.getElementById('buktiModal').addEventListener('click', function(e){
  if (e.target === this) closeBukti();
});
</script>
</body>
</html>