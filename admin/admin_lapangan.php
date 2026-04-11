<?php
session_start();
include '../koneksi.php';

// ── HANDLE POST: TAMBAH / EDIT / TOGGLE STATUS ────────────────────────────
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $id         = intval($_POST['id'] ?? 0);
        $nama       = $koneksi->real_escape_string(trim($_POST['nama'] ?? ''));
        $jenis      = $koneksi->real_escape_string($_POST['jenis'] ?? 'sintetis');
        $harga      = intval($_POST['harga_per_jam'] ?? 0);
        $fasilitas  = $koneksi->real_escape_string(trim($_POST['fasilitas'] ?? ''));
        $status     = $koneksi->real_escape_string($_POST['status'] ?? 'tersedia');

        if ($action === 'tambah') {
            $koneksi->query("INSERT INTO lapangan (nama, jenis, harga_per_jam, fasilitas, status) VALUES ('$nama','$jenis',$harga,'$fasilitas','$status')");
            $msg = 'Lapangan berhasil ditambahkan.';
        } else {
            $koneksi->query("UPDATE lapangan SET nama='$nama', jenis='$jenis', harga_per_jam=$harga, fasilitas='$fasilitas', status='$status' WHERE id=$id");
            $msg = 'Lapangan berhasil diupdate.';
        }
    } elseif ($action === 'toggle_status') {
        $id         = intval($_POST['id'] ?? 0);
        $new_status = $koneksi->real_escape_string($_POST['new_status'] ?? 'tersedia');
        $koneksi->query("UPDATE lapangan SET status='$new_status' WHERE id=$id");
        $msg = 'Status lapangan berhasil diubah.';
    } elseif ($action === 'hapus') {
        $id = intval($_POST['id'] ?? 0);
        $koneksi->query("DELETE FROM lapangan WHERE id=$id");
        $msg = 'Lapangan berhasil dihapus.';
    }

    header('Location: admin_lapangan.php?msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// ── AMBIL DATA LAPANGAN + STATISTIK ──────────────────────────────────────
$bulan_ini_awal  = date('Y-m-01');
$bulan_ini_akhir = date('Y-m-t');

$result = $koneksi->query("
    SELECT l.*,
        (SELECT COUNT(*) FROM booking b WHERE b.lapangan_id = l.id
         AND b.tanggal BETWEEN '$bulan_ini_awal' AND '$bulan_ini_akhir'
         AND b.status IN ('selesai','dikonfirmasi')) as booking_bulan_ini,
        (SELECT COALESCE(SUM(b.total_harga),0) FROM booking b WHERE b.lapangan_id = l.id
         AND b.tanggal BETWEEN '$bulan_ini_awal' AND '$bulan_ini_akhir'
         AND b.status IN ('selesai','dikonfirmasi')) as pendapatan_bulan_ini,
        (SELECT COUNT(*) FROM booking b WHERE b.lapangan_id = l.id
         AND b.tanggal = CURDATE()
         AND b.status IN ('dikonfirmasi','pending')) as booking_hari_ini
    FROM lapangan l
    ORDER BY l.id ASC
");
$fields = [];
while ($row = $result->fetch_assoc()) $fields[] = $row;

// ── HELPERS ───────────────────────────────────────────────────────────────
function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function rupiahShort($n) {
    if ($n >= 1000000) return 'Rp ' . number_format($n/1000000,1,',','.') . 'jt';
    if ($n >= 1000)    return 'Rp ' . number_format($n/1000,0,',','.') . 'rb';
    return 'Rp ' . $n;
}
function occColor($occ) {
    if ($occ == 0) return 'text-red-400';
    if ($occ < 60) return 'text-yellow-400';
    return 'text-green-400';
}

// Hitung occupancy: jumlah jam terpakai / total jam operasional (14 jam: 08.00-22.00) per bulan
$days_in_month = intval(date('t'));
$total_jam_ops = 14 * $days_in_month; // 14 jam/hari

$court_icons  = ['sintetis'=>'⚽','vinyl'=>'🏟️','rumput'=>'🌿'];
$court_colors = ['sintetis'=>'from-neutral-900 to-green-950','vinyl'=>'from-neutral-900 to-red-950','rumput'=>'from-neutral-900 to-emerald-950'];

// ── TANGGAL ───────────────────────────────────────────────────────────────
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');

// Pending pembayaran untuk badge sidebar
$r = $koneksi->query("SELECT COUNT(*) as c FROM pembayaran WHERE status='pending'");
$pending = $r->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartFutsal — Data Lapangan</title>
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
    .progress-fill { background:linear-gradient(90deg,#e8192c,#ff3344); }
    .modal-backdrop { backdrop-filter:blur(4px); }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .modal-box { animation:fadeUp .25s ease; }
    input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1); opacity:1; cursor:pointer; }
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
      ['href'=>'admin_dashboard.php','icon'=>'📊','label'=>'Dashboard',       'badge'=>null,    'active'=>false],
      ['href'=>'admin_jadwal.php',   'icon'=>'📅','label'=>'Jadwal Lapangan', 'badge'=>null,    'active'=>false],
      ['href'=>'admin_booking.php',  'icon'=>'📋','label'=>'Semua Booking',   'badge'=>$pending,'active'=>false],
      ['href'=>'admin_lapangan.php', 'icon'=>'🏟️','label'=>'Data Lapangan',  'badge'=>null,    'active'=>true],
      ['href'=>'pembayaran.php',     'icon'=>'💳','label'=>'Pembayaran',      'badge'=>$pending,'active'=>false],
      ['href'=>'laporan.php',        'icon'=>'📈','label'=>'Laporan',         'badge'=>null,    'active'=>false],
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
      <h1 class="font-display text-xl tracking-widest">Data Lapangan</h1>
      <p class="text-xs text-gray-500"><?= $today_str ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 rounded-lg px-3 py-2 border border-white/5 text-sm text-gray-500" style="background:#1a1a1a; width:220px">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Cari lapangan…" class="bg-transparent outline-none text-white text-xs w-full placeholder-gray-600"
               oninput="filterFields(this.value)" />
      </div>
      <div class="relative w-9 h-9 rounded-lg flex items-center justify-center border border-white/5 cursor-pointer hover:bg-white/5 transition text-base" style="background:#1a1a1a">
        🔔<div class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-brand-red rounded-full border border-neutral-800"></div>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="p-7 flex-1">

    <?php if ($msg): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-semibold bg-green-500/10 text-green-400 border border-green-500/20">
      ✅ <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-6">
      <div class="text-sm text-gray-500">Kelola data dan status lapangan · <span class="text-white"><?= count($fields) ?> lapangan terdaftar</span></div>
      <button onclick="openAddModal()" class="bg-brand-red text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-brand-red2 transition">
        + Tambah Lapangan
      </button>
    </div>

    <!-- FIELD CARDS -->
    <div id="fieldGrid" class="grid grid-cols-2 gap-4">
      <?php foreach ($fields as $f):
        $icon  = $court_icons[$f['jenis']] ?? '🏟️';
        $color = $court_colors[$f['jenis']] ?? 'from-neutral-900 to-red-950';
        $isActive = $f['status'] === 'tersedia';
        $isMaint  = $f['status'] === 'maintenance';
        // Hitung occupancy sederhana dari booking bulan ini (jam terpakai / total jam ops)
        $r = $koneksi->query("
            SELECT COALESCE(SUM(durasi_jam),0) as jam
            FROM booking WHERE lapangan_id={$f['id']}
            AND tanggal BETWEEN '$bulan_ini_awal' AND '$bulan_ini_akhir'
            AND status IN ('selesai','dikonfirmasi')
        ");
        $jam_terpakai = $r->fetch_assoc()['jam'];
        $occ = $total_jam_ops > 0 ? min(100, round($jam_terpakai / $total_jam_ops * 100)) : 0;
      ?>
      <div class="field-card card-hover rounded-xl border border-white/5 overflow-hidden" style="background:#1c1c1c"
           data-name="<?= strtolower(htmlspecialchars($f['nama'])) ?>">
        <!-- Banner -->
        <div class="h-28 relative flex items-center justify-center text-5xl bg-gradient-to-br <?= $color ?>">
          <?= $icon ?>
          <div class="absolute top-3 right-3">
            <?php if ($isActive): ?>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-500/15 text-green-400 border border-green-500/30">Aktif</span>
            <?php elseif ($isMaint): ?>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-500/15 text-yellow-400 border border-yellow-500/30">Maintenance</span>
            <?php else: ?>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-500/15 text-red-400 border border-red-500/30">Tidak Tersedia</span>
            <?php endif; ?>
          </div>
          <?php if ($f['booking_hari_ini'] > 0): ?>
          <div class="absolute bottom-2 left-3">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-black/50 text-white">
              📅 <?= $f['booking_hari_ini'] ?> booking hari ini
            </span>
          </div>
          <?php endif; ?>
        </div>
        <!-- Body -->
        <div class="p-5">
          <div class="text-base font-bold mb-0.5"><?= htmlspecialchars($f['nama']) ?></div>
          <div class="text-xs text-gray-500 mb-4 capitalize"><?= $f['jenis'] ?> · <?= htmlspecialchars($f['fasilitas'] ?? '-') ?></div>
          <!-- Stats -->
          <div class="flex gap-5 mb-4">
            <div>
              <div class="text-base font-bold"><?= rupiahShort($f['harga_per_jam']) ?></div>
              <div class="text-xs text-gray-500">Per jam</div>
            </div>
            <div>
              <div class="text-base font-bold <?= occColor($occ) ?>"><?= $occ ?>%</div>
              <div class="text-xs text-gray-500">Okupansi</div>
            </div>
            <div>
              <div class="text-base font-bold text-white"><?= $f['booking_bulan_ini'] ?></div>
              <div class="text-xs text-gray-500">Booking bln ini</div>
            </div>
            <div>
              <div class="text-base font-bold text-green-400"><?= rupiahShort($f['pendapatan_bulan_ini']) ?></div>
              <div class="text-xs text-gray-500">Pendapatan</div>
            </div>
          </div>
          <!-- Progress bar -->
          <div class="h-1.5 rounded-full overflow-hidden mb-4" style="background:#1a1a1a">
            <div class="h-full rounded-full transition-all"
                 style="width:<?= $occ ?>%;
                        background:<?= $occ===0 ? '#e8192c' : ($occ<60 ? '#f59e0b' : 'linear-gradient(90deg,#e8192c,#ff3344)') ?>">
            </div>
          </div>
          <!-- Action Buttons -->
          <div class="flex gap-2">
            <button onclick='openEditModal(<?= json_encode($f) ?>)'
                    class="flex-1 text-xs py-2 rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 transition">
              ✏️ Edit Detail
            </button>
            <a href="admin_jadwal.php?lapangan=<?= $f['id'] ?>"
               class="flex-1 text-xs py-2 rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 transition text-center no-underline">
              📅 Lihat Jadwal
            </a>
            <?php if ($isMaint || $f['status'] === 'tidak_tersedia'): ?>
            <form method="POST" class="flex-1">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $f['id'] ?>">
              <input type="hidden" name="new_status" value="tersedia">
              <button type="submit" class="w-full text-xs py-2 rounded-lg border border-green-500/30 text-green-400 hover:bg-green-500/10 transition">
                ✅ Aktifkan
              </button>
            </form>
            <?php else: ?>
            <form method="POST" class="flex-1" onsubmit="return confirm('Set lapangan ke maintenance?')">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $f['id'] ?>">
              <input type="hidden" name="new_status" value="maintenance">
              <button type="submit" class="w-full text-xs py-2 rounded-lg border border-yellow-500/30 text-yellow-400 hover:bg-yellow-500/10 transition">
                🔧 Maintenance
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($fields)): ?>
      <div class="col-span-2 text-center py-16 text-gray-600">
        <div class="text-4xl mb-3">🏟️</div>
        <div class="text-sm">Belum ada lapangan. Tambahkan lapangan pertama kamu!</div>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- FAB -->
<button onclick="openAddModal()"
  class="fixed bottom-7 right-7 rounded-full bg-brand-red text-white text-2xl flex items-center justify-center shadow-lg z-40 hover:scale-110 transition-transform"
  style="width:52px;height:52px;box-shadow:0 4px 20px rgba(232,25,44,0.4)">＋</button>

<!-- ═══════════ MODAL TAMBAH/EDIT LAPANGAN ═══════════ -->
<div id="lapanganModal" class="modal-backdrop fixed inset-0 bg-black/70 z-50 hidden items-center justify-center"
     onclick="if(event.target===this)closeModal()">
  <div class="modal-box rounded-2xl p-7 w-[500px] max-w-[95vw] border border-white/5" style="background:#1c1c1c">
    <div id="modalTitle" class="font-display text-2xl tracking-widest mb-1">Tambah Lapangan</div>
    <div class="text-xs text-gray-500 mb-5">Isi detail lapangan di bawah</div>
    <form method="POST" id="lapanganForm">
      <input type="hidden" name="action" id="formAction" value="tambah">
      <input type="hidden" name="id"     id="formId"     value="">
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="flex flex-col gap-1.5 col-span-2">
          <label class="text-xs text-gray-500">Nama Lapangan</label>
          <input type="text" name="nama" id="fNama" required placeholder="cth: Arena A"
                 class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs text-gray-500">Jenis Lapangan</label>
          <select name="jenis" id="fJenis" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
            <option value="sintetis">Sintetis</option>
            <option value="vinyl">Vinyl</option>
            <option value="rumput">Rumput</option>
          </select>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs text-gray-500">Harga per Jam (Rp)</label>
          <input type="number" name="harga_per_jam" id="fHarga" required placeholder="cth: 140000" min="0"
                 class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
        </div>
        <div class="flex flex-col gap-1.5 col-span-2">
          <label class="text-xs text-gray-500">Fasilitas</label>
          <input type="text" name="fasilitas" id="fFasilitas" placeholder="cth: AC, Toilet, Parkir"
                 class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a" />
        </div>
        <div class="flex flex-col gap-1.5 col-span-2">
          <label class="text-xs text-gray-500">Status</label>
          <select name="status" id="fStatus" class="rounded-lg px-3 py-2.5 text-sm text-white border border-white/5 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
            <option value="tersedia">Tersedia</option>
            <option value="maintenance">Maintenance</option>
            <option value="tidak_tersedia">Tidak Tersedia</option>
          </select>
        </div>
      </div>
      <div class="flex gap-2.5 mt-2">
        <button type="button" onclick="closeModal()" class="flex-1 py-2.5 rounded-lg border border-white/10 text-gray-400 text-sm hover:bg-white/5 transition">Batal</button>
        <button type="submit" class="flex-[2] py-2.5 rounded-lg bg-brand-red text-white text-sm font-semibold hover:bg-brand-red2 transition">✓ Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('modalTitle').textContent = 'Tambah Lapangan';
  document.getElementById('formAction').value = 'tambah';
  document.getElementById('formId').value = '';
  document.getElementById('fNama').value = '';
  document.getElementById('fJenis').value = 'sintetis';
  document.getElementById('fHarga').value = '';
  document.getElementById('fFasilitas').value = '';
  document.getElementById('fStatus').value = 'tersedia';
  const m = document.getElementById('lapanganModal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function openEditModal(f) {
  document.getElementById('modalTitle').textContent = 'Edit Lapangan';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('formId').value = f.id;
  document.getElementById('fNama').value = f.nama;
  document.getElementById('fJenis').value = f.jenis;
  document.getElementById('fHarga').value = f.harga_per_jam;
  document.getElementById('fFasilitas').value = f.fasilitas || '';
  document.getElementById('fStatus').value = f.status;
  const m = document.getElementById('lapanganModal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function closeModal() {
  const m = document.getElementById('lapanganModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
function filterFields(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.field-card').forEach(card => {
    card.style.display = card.dataset.name.includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>