<?php
session_start();
include '../koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: login.php'); exit;
}
$user_id = intval($_SESSION['user_id']);

$msg = ''; $msg_type = '';

// ── HANDLE RESCHEDULE ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reschedule') {
        $bid         = intval($_POST['booking_id'] ?? 0);
        $tanggal_baru = $koneksi->real_escape_string($_POST['tanggal_baru'] ?? '');
        $jam_baru     = $koneksi->real_escape_string($_POST['jam_mulai_baru'] ?? '');
        $durasi       = intval($_POST['durasi_baru'] ?? 1);

        // Validasi kepemilikan booking
        $rB = $koneksi->query("SELECT * FROM booking WHERE id=$bid AND user_id=$user_id AND status IN ('pending','dikonfirmasi')");
        if ($rB->num_rows === 0) {
            $msg = 'Booking tidak ditemukan atau tidak bisa diubah.'; $msg_type = 'error';
        } else {
            $bData = $rB->fetch_assoc();
            $jam_selesai_ts = strtotime("$tanggal_baru $jam_baru") + ($durasi * 3600);
            $jam_selesai_baru = date('H:i:s', $jam_selesai_ts);

            if ($tanggal_baru < date('Y-m-d')) {
                $msg = 'Tanggal baru tidak boleh di masa lalu.'; $msg_type = 'error';
            } elseif ($jam_selesai_baru > '22:00:00') {
                $msg = 'Jam selesai melebihi jam operasional (22:00).'; $msg_type = 'error';
            } else {
                $harga_per_jam = intval($bData['harga_saat_booking']);
                $total_baru    = $harga_per_jam * $durasi;
                $sql = "UPDATE booking SET tanggal='$tanggal_baru', jam_mulai='$jam_baru', jam_selesai='$jam_selesai_baru', durasi_jam=$durasi, total_harga=$total_baru WHERE id=$bid";
                if ($koneksi->query($sql)) {
                    $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe) VALUES ($user_id, $bid, 'Booking Direschedule', 'Jadwal booking #{$bData['kode_booking']} telah diubah ke $tanggal_baru pukul $jam_baru.', 'info')");
                    $msg = 'Jadwal berhasil diubah!'; $msg_type = 'success';
                } else {
                    $msg = 'Jadwal bentrok atau lapangan diblokir. Pilih waktu lain.'; $msg_type = 'error';
                }
            }
        }
    } elseif ($action === 'batalkan') {
        $bid = intval($_POST['booking_id'] ?? 0);
        $rB  = $koneksi->query("SELECT * FROM booking WHERE id=$bid AND user_id=$user_id AND status IN ('pending','dikonfirmasi')");
        if ($rB->num_rows === 0) {
            $msg = 'Booking tidak ditemukan.'; $msg_type = 'error';
        } else {
            $bData = $rB->fetch_assoc();
            // Validasi batas pembatalan: minimal 3 jam sebelum jam main
            $waktu_main = strtotime($bData['tanggal'] . ' ' . $bData['jam_mulai']);
            $batas_batal = $waktu_main - (3 * 3600);
            if (time() >= $batas_batal) {
                $msg = 'Pembatalan tidak bisa dilakukan. Sudah kurang dari 3 jam sebelum jadwal main.'; $msg_type = 'error';
            } else {
                $koneksi->query("UPDATE booking SET status='dibatalkan' WHERE id=$bid");
                $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe) VALUES ($user_id, $bid, 'Booking Dibatalkan', 'Booking {$bData['kode_booking']} telah dibatalkan.', 'warning')");
                $msg = 'Booking berhasil dibatalkan.'; $msg_type = 'success';
            }
        }
    }

    header('Location: riwayat.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
    exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['type'] ?? 'info'; }

// ── AMBIL DATA BOOKING AKTIF (pending + dikonfirmasi) ────────────────────
$aktif_result = $koneksi->query("
    SELECT b.*, l.nama as nama_lapangan, l.jenis, l.harga_per_jam,
           p.status as status_bayar, p.metode
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    LEFT JOIN pembayaran p ON p.booking_id = b.id
    WHERE b.user_id = $user_id
    AND b.status IN ('pending','dikonfirmasi')
    ORDER BY b.tanggal ASC, b.jam_mulai ASC
");
$aktif = [];
while ($row = $aktif_result->fetch_assoc()) $aktif[] = $row;

// ── AMBIL DATA BOOKING SELESAI/BATAL ─────────────────────────────────────
$selesai_result = $koneksi->query("
    SELECT b.*, l.nama as nama_lapangan, l.jenis,
           p.status as status_bayar, p.metode, p.paid_at
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    LEFT JOIN pembayaran p ON p.booking_id = b.id
    WHERE b.user_id = $user_id
    AND b.status IN ('selesai','dibatalkan')
    ORDER BY b.tanggal DESC, b.jam_mulai DESC
    LIMIT 20
");
$selesai = [];
while ($row = $selesai_result->fetch_assoc()) $selesai[] = $row;

// ── AMBIL LAPANGAN UNTUK FORM RESCHEDULE ─────────────────────────────────
$lapangans = [];
$rLap = $koneksi->query("SELECT * FROM lapangan WHERE status = 'tersedia' ORDER BY nama ASC");
while ($row = $rLap->fetch_assoc()) $lapangans[] = $row;

// ── HELPERS ───────────────────────────────────────────────────────────────
function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function batasBatal($tanggal, $jam_mulai) {
    return strtotime($tanggal . ' ' . $jam_mulai) - (3 * 3600);
}
function canCancel($tanggal, $jam_mulai) {
    return time() < batasBatal($tanggal, $jam_mulai);
}
function cancelSisa($tanggal, $jam_mulai) {
    $sisa = batasBatal($tanggal, $jam_mulai) - time();
    if ($sisa <= 0) return 'Waktu habis';
    $j = floor($sisa/3600); $m = floor(($sisa%3600)/60);
    return "Sisa " . ($j > 0 ? "{$j}j " : '') . "{$m}m untuk batal";
}

$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');
$rUser = $koneksi->query("SELECT nama FROM users WHERE id=$user_id");
$user  = $rUser->fetch_assoc();
$rNotif = $koneksi->query("SELECT COUNT(*) as c FROM notifikasi WHERE user_id=$user_id AND is_read=0");
$unread = $rNotif->fetch_assoc()['c'];
$jenis_icon = ['sintetis'=>'⚽','vinyl'=>'🏟️','rumput'=>'🌿'];
$jam_ops = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartFutsal — Riwayat Booking</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif'],display:['Bebas Neue','sans-serif']},colors:{brand:{red:'#e8192c',red2:'#ff3344'}}}}}</script>
  <style>
    body{background:#0a0a0a;color:#f0f0f0}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:#2a2a2a;border-radius:4px}
    .nav-link{transition:all .15s;padding-bottom:2px}
    .nav-link.active{color:#ff3344;border-bottom:2px solid #e8192c}
    .card{background:#161616;border:1px solid rgba(255,255,255,0.06)}
    .badge-pending{background:rgba(245,158,11,.15);color:#f59e0b;border:1px solid rgba(245,158,11,.3)}
    .badge-dikonfirmasi{background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.3)}
    .badge-selesai{background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid rgba(59,130,246,.3)}
    .badge-dibatalkan{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp .3s ease forwards}
    .modal-bg{backdrop-filter:blur(6px)}
    @keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
    .modal-box{animation:modalIn .2s ease}
    input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);cursor:pointer}
  </style>
</head>
<body class="font-sans min-h-screen">

<!-- NAVBAR -->
<nav class="sticky top-0 z-50 border-b border-white/5" style="background:#111">
  <div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-brand-red flex items-center justify-center">⚽</div>
      <span class="font-display text-lg tracking-wider">Smart<span class="text-brand-red">Futsal</span></span>
    </div>
    <div class="flex items-center gap-6">
      <a href="booking.php"    class="nav-link text-sm text-gray-400 no-underline">Booking</a>
      <a href="riwayat.php"    class="nav-link active text-sm font-semibold no-underline text-white">Riwayat</a>
      <a href="notifikasi.php" class="nav-link text-sm text-gray-400 no-underline relative">
        Notifikasi<?php if($unread>0):?><span class="absolute -top-2 -right-3 w-4 h-4 bg-brand-red rounded-full text-xs flex items-center justify-center font-bold"><?=$unread?></span><?php endif;?>
      </a>
      <div class="flex items-center gap-2 pl-4 border-l border-white/10">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#e8192c,#800010)"><?=strtoupper(substr($user['nama']??'U',0,1))?></div>
        <span class="text-sm text-gray-300"><?=htmlspecialchars($user['nama']??'')?></span>
        <a href="logout.php" class="ml-2 text-xs text-gray-600 hover:text-red-400 transition no-underline">Keluar</a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<div style="background:linear-gradient(180deg,#0d1a0d 0%,#0a0a0a 100%)">
  <div class="max-w-5xl mx-auto px-5 py-10 fade-up">
    <div class="text-xs text-green-500 tracking-widest uppercase font-semibold mb-2">📋 Histori Reservasi</div>
    <h1 class="font-display text-5xl tracking-widest mb-2">RIWAYAT <span class="text-green-400">BOOKING</span></h1>
    <p class="text-gray-500 text-sm">Kelola dan pantau semua reservasi lapangan kamu.</p>
  </div>
</div>

<div class="max-w-5xl mx-auto px-5 pb-16 mt-2">

  <?php if($msg):?>
  <div class="mb-5 px-4 py-3 rounded-xl text-sm font-semibold fade-up
    <?=$msg_type==='success'?'bg-green-500/10 text-green-400 border border-green-500/20':'bg-red-500/10 text-red-400 border border-red-500/20'?>">
    <?=$msg_type==='success'?'✅':'❌'?> <?=htmlspecialchars($msg)?>
  </div>
  <?php endif;?>

  <!-- ════════ BOOKING AKTIF ════════ -->
  <div class="mb-10">
    <div class="flex items-center gap-3 mb-5">
      <div class="font-display text-2xl tracking-wider">BOOKING <span class="text-brand-red">AKTIF</span></div>
      <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-brand-red/10 text-brand-red border border-brand-red/20"><?=count($aktif)?></span>
    </div>

    <?php if(empty($aktif)):?>
    <div class="card rounded-2xl p-10 text-center fade-up">
      <div class="text-4xl mb-3">📭</div>
      <div class="text-gray-500 text-sm">Belum ada booking aktif.</div>
      <a href="booking.php" class="inline-block mt-3 px-5 py-2.5 rounded-xl bg-brand-red text-white text-sm font-semibold no-underline hover:opacity-90 transition">+ Booking Sekarang</a>
    </div>
    <?php else:?>
    <div class="space-y-4">
    <?php foreach($aktif as $b):
      $icon = $jenis_icon[$b['jenis']] ?? '🏟️';
      $bisa_batal = canCancel($b['tanggal'], $b['jam_mulai']);
      $sisa_info  = cancelSisa($b['tanggal'], $b['jam_mulai']);
      $tgl_fmt    = date('d M Y', strtotime($b['tanggal']));
      $waktu_main = strtotime($b['tanggal'].' '.$b['jam_mulai']);
      $is_hari_ini = $b['tanggal'] === date('Y-m-d');
      $is_besok    = $b['tanggal'] === date('Y-m-d', strtotime('+1 day'));
    ?>
    <div class="card rounded-2xl overflow-hidden fade-up">
      <!-- Top bar warna status -->
      <div class="h-1 w-full <?=$b['status']==='dikonfirmasi'?'bg-green-500':'bg-yellow-500'?>"></div>
      <div class="p-5">
        <div class="flex items-start justify-between gap-4">
          <!-- Info utama -->
          <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl flex-shrink-0"
                 style="background:<?=$b['status']==='dikonfirmasi'?'rgba(34,197,94,.1)':'rgba(245,158,11,.1)'?>">
              <?=$icon?>
            </div>
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="font-mono text-xs text-gray-500"><?=htmlspecialchars($b['kode_booking'])?></span>
                <span class="badge-<?=$b['status']?> text-xs font-bold px-2.5 py-0.5 rounded-full"><?=ucfirst($b['status'])?></span>
                <?php if($is_hari_ini):?><span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-brand-red/10 text-brand-red border border-brand-red/20">Hari Ini!</span><?php elseif($is_besok):?><span class="text-xs font-bold px-2.5 py-0.5 rounded-full" style="background:rgba(139,92,246,.1);color:#8b5cf6;border:1px solid rgba(139,92,246,.3)">Besok</span><?php endif;?>
              </div>
              <div class="font-bold text-lg"><?=htmlspecialchars($b['nama_lapangan'])?></div>
              <div class="text-sm text-gray-400 mt-0.5">
                📅 <?=$tgl_fmt?> &nbsp;·&nbsp; ⏰ <?=substr($b['jam_mulai'],0,5)?>–<?=substr($b['jam_selesai'],0,5)?> &nbsp;·&nbsp; ⌛ <?=$b['durasi_jam']?> jam
              </div>
            </div>
          </div>
          <!-- Harga -->
          <div class="text-right flex-shrink-0">
            <div class="font-display text-xl tracking-wide text-white"><?=rupiah($b['total_harga'])?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?=rupiah($b['harga_saat_booking'])?>/jam</div>
          </div>
        </div>

        <!-- Status pembayaran -->
        <?php if($b['status_bayar']):?>
        <div class="mt-3 flex items-center gap-2">
          <span class="text-xs text-gray-500">Pembayaran:</span>
          <span class="text-xs font-semibold <?=$b['status_bayar']==='berhasil'?'text-green-400':($b['status_bayar']==='pending'?'text-yellow-400':'text-red-400')?>">
            <?=$b['status_bayar']==='berhasil'?'✅ Lunas':($b['status_bayar']==='pending'?'⏳ Menunggu konfirmasi':'❌ Gagal')?>
          </span>
          <?php if($b['metode']):?><span class="text-xs text-gray-600">via <?=ucfirst($b['metode'])?></span><?php endif;?>
        </div>
        <?php else:?>
        <div class="mt-3 flex items-center gap-2">
          <span class="text-xs text-gray-600">💳 Belum ada data pembayaran. Segera lakukan pembayaran.</span>
        </div>
        <?php endif;?>

        <!-- Tombol aksi -->
        <div class="flex items-center gap-2 mt-4">
          <button onclick="openReschedule(<?=htmlspecialchars(json_encode($b))?>, <?=json_encode($lapangans)?>)"
                  class="flex-1 py-2.5 rounded-xl text-xs font-semibold border border-blue-500/30 text-blue-400 hover:bg-blue-500/10 transition">
            🔄 Reschedule
          </button>

          <?php if($bisa_batal):?>
          <button onclick="confirmBatal(<?=$b['id']?>, '<?=htmlspecialchars($b['kode_booking'])?>')"
                  class="flex-1 py-2.5 rounded-xl text-xs font-semibold border border-red-500/30 text-red-400 hover:bg-red-500/10 transition">
            ❌ Batalkan
          </button>
          <?php else:?>
          <div class="flex-1 py-2.5 rounded-xl text-xs font-semibold border border-white/5 text-gray-600 text-center cursor-not-allowed"
               title="Pembatalan hanya bisa dilakukan minimal 3 jam sebelum jadwal main">
            🔒 Tidak Bisa Dibatalkan
          </div>
          <?php endif;?>

          <div class="text-xs text-gray-600 ml-1"><?=!$bisa_batal?'':'⚠ '.$sisa_info?></div>
        </div>

        <?php if(!$bisa_batal && $b['status']!=='dibatalkan'):?>
        <div class="mt-2 text-xs text-red-400/60">🔒 Pembatalan ditutup karena kurang dari 3 jam sebelum jadwal.</div>
        <?php endif;?>

      </div>
    </div>
    <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

  <!-- ════════ RIWAYAT SELESAI/BATAL ════════ -->
  <div>
    <div class="flex items-center gap-3 mb-5">
      <div class="font-display text-2xl tracking-wider">RIWAYAT <span class="text-gray-400">SELESAI</span></div>
      <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/5 text-gray-400 border border-white/10"><?=count($selesai)?></span>
    </div>

    <?php if(empty($selesai)):?>
    <div class="card rounded-2xl p-8 text-center fade-up">
      <div class="text-3xl mb-3">📂</div>
      <div class="text-gray-600 text-sm">Belum ada riwayat booking selesai.</div>
    </div>
    <?php else:?>
    <div class="card rounded-2xl overflow-hidden fade-up">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/5 text-xs text-gray-500 uppercase tracking-wider">
            <th class="px-5 py-3 text-left">Booking</th>
            <th class="px-5 py-3 text-left">Lapangan</th>
            <th class="px-5 py-3 text-left">Tanggal & Jam</th>
            <th class="px-5 py-3 text-right">Total</th>
            <th class="px-5 py-3 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($selesai as $b):?>
          <tr class="border-b border-white/[0.04] last:border-0 hover:bg-white/[0.02] transition">
            <td class="px-5 py-3">
              <div class="font-mono text-xs text-gray-400"><?=htmlspecialchars($b['kode_booking'])?></div>
              <div class="text-xs text-gray-600 mt-0.5"><?=$jenis_icon[$b['jenis']]??'🏟️'?> <?=ucfirst($b['jenis'])?></div>
            </td>
            <td class="px-5 py-3 font-semibold text-white"><?=htmlspecialchars($b['nama_lapangan'])?></td>
            <td class="px-5 py-3">
              <div class="text-white text-xs"><?=date('d M Y',strtotime($b['tanggal']))?></div>
              <div class="text-gray-500 text-xs"><?=substr($b['jam_mulai'],0,5)?>–<?=substr($b['jam_selesai'],0,5)?> · <?=$b['durasi_jam']?> jam</div>
            </td>
            <td class="px-5 py-3 text-right font-bold text-white"><?=rupiah($b['total_harga'])?></td>
            <td class="px-5 py-3">
              <span class="badge-<?=$b['status']?> text-xs font-bold px-2.5 py-1 rounded-full">
                <?=$b['status']==='selesai'?'✅ Selesai':'❌ Dibatalkan'?>
              </span>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
  </div>

</div>

<!-- ═══════ MODAL RESCHEDULE ═══════ -->
<div id="rescheduleModal" class="modal-bg fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)closeReschedule()">
  <div class="modal-box rounded-2xl border border-white/10 p-6 w-full max-w-md" style="background:#161616">
    <div class="font-display text-2xl tracking-wider mb-1">RESCHEDULE <span class="text-brand-red">BOOKING</span></div>
    <div id="rs_kode" class="text-xs text-gray-500 mb-5 font-mono"></div>
    <form method="POST" id="rescheduleForm">
      <input type="hidden" name="action"     value="reschedule">
      <input type="hidden" name="booking_id" id="rs_bid" value="">
      <div class="space-y-3">
        <div>
          <label class="text-xs text-gray-500 block mb-1.5">Tanggal Baru</label>
          <input type="date" name="tanggal_baru" id="rs_tanggal" min="<?=date('Y-m-d')?>" required
                 class="w-full rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a"/>
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1.5">Jam Mulai Baru</label>
          <select name="jam_mulai_baru" id="rs_jam" required
                  class="w-full rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
            <?php foreach($jam_ops as $j):?><option value="<?=$j?>"><?=$j?></option><?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1.5">Durasi</label>
          <select name="durasi_baru" id="rs_durasi"
                  class="w-full rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
            <option value="1">1 Jam</option>
            <option value="2" selected>2 Jam</option>
            <option value="3">3 Jam</option>
            <option value="4">4 Jam</option>
          </select>
        </div>
        <div class="rounded-xl p-3 text-xs text-yellow-400 border border-yellow-500/20" style="background:rgba(245,158,11,.05)">
          ⚠ Reschedule akan mengubah tanggal dan jam main. Konfirmasi pembayaran tetap berlaku.
        </div>
      </div>
      <div class="flex gap-3 mt-5">
        <button type="button" onclick="closeReschedule()" class="flex-1 py-2.5 rounded-xl border border-white/10 text-gray-400 text-sm hover:bg-white/5 transition">Batal</button>
        <button type="submit" class="flex-[2] py-2.5 rounded-xl bg-brand-red text-white text-sm font-semibold hover:opacity-90 transition">🔄 Konfirmasi Reschedule</button>
      </div>
    </form>
  </div>
</div>

<!-- FORM BATALKAN (hidden) -->
<form id="batalForm" method="POST" class="hidden">
  <input type="hidden" name="action"     value="batalkan">
  <input type="hidden" name="booking_id" id="batal_bid" value="">
</form>

<script>
function openReschedule(b) {
  document.getElementById('rs_bid').value    = b.id;
  document.getElementById('rs_kode').textContent = 'Kode: ' + b.kode_booking + ' · ' + b.nama_lapangan;
  document.getElementById('rs_tanggal').value = b.tanggal;
  // Set jam
  const sel = document.getElementById('rs_jam');
  const jam = b.jam_mulai.substring(0,5);
  for(let o of sel.options) if(o.value === jam) o.selected = true;
  // Set durasi
  const dur = document.getElementById('rs_durasi');
  for(let o of dur.options) if(parseInt(o.value)===parseInt(b.durasi_jam)) o.selected=true;
  const m = document.getElementById('rescheduleModal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function closeReschedule(){
  const m = document.getElementById('rescheduleModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
function confirmBatal(bid, kode){
  if(confirm('Yakin ingin membatalkan booking ' + kode + '?\nPembatalan tidak bisa dibatalkan.')){
    document.getElementById('batal_bid').value = bid;
    document.getElementById('batalForm').submit();
  }
}
</script>
</body>
</html>