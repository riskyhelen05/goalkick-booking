<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);

// ── TANDAI SEMUA DIBACA ───────────────────────────────────────────────────
if (isset($_GET['mark_all'])) {
    $koneksi->query("UPDATE notifikasi SET is_read=1 WHERE user_id=$user_id");
    header('Location: notifikasi.php'); exit;
}
// Tandai satu dibaca
if (isset($_GET['read'])) {
    $nid = intval($_GET['read']);
    $koneksi->query("UPDATE notifikasi SET is_read=1 WHERE id=$nid AND user_id=$user_id");
    header('Location: notifikasi.php'); exit;
}

// ── AUTO-GENERATE NOTIFIKASI PENGINGAT ────────────────────────────────────
// Cek booking yang mau main dalam 2 jam ke depan, belum ada notifikasi pengingat
$dua_jam_lagi = date('Y-m-d H:i:s', strtotime('+2 hours'));
$sekarang     = date('Y-m-d H:i:s');
$rReminder = $koneksi->query("
    SELECT b.id, b.kode_booking, b.tanggal, b.jam_mulai, l.nama as nama_lapangan
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    WHERE b.user_id = $user_id
    AND b.status = 'dikonfirmasi'
    AND CONCAT(b.tanggal, ' ', b.jam_mulai) BETWEEN '$sekarang' AND '$dua_jam_lagi'
    AND b.id NOT IN (
        SELECT booking_id FROM notifikasi
        WHERE user_id=$user_id AND judul LIKE '%Segera Dimulai%'
    )
");
while ($r = $rReminder->fetch_assoc()) {
    $waktu = date('H:i', strtotime($r['jam_mulai']));
    $tgl   = date('d M', strtotime($r['tanggal']));
    $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe)
        VALUES ($user_id, {$r['id']}, '⏰ Pertandingan Segera Dimulai!',
        'Booking {$r['kode_booking']} di {$r['nama_lapangan']} akan dimulai pukul $waktu WIB pada $tgl. Jangan telat!', 'warning')");
}

// ── FILTER ────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'semua';
$valid  = ['semua','info','warning','success','unread'];
if (!in_array($filter, $valid)) $filter = 'semua';

$where = "WHERE n.user_id = $user_id";
if ($filter === 'unread')       $where .= " AND n.is_read = 0";
elseif ($filter !== 'semua')    $where .= " AND n.tipe = '$filter'";

// ── AMBIL NOTIFIKASI ──────────────────────────────────────────────────────
$notifs = [];
$result = $koneksi->query("
    SELECT n.*, b.kode_booking, b.tanggal, b.jam_mulai, b.jam_selesai,
           l.nama as nama_lapangan
    FROM notifikasi n
    JOIN booking b ON n.booking_id = b.id
    JOIN lapangan l ON b.lapangan_id = l.id
    $where
    ORDER BY n.sent_at DESC
    LIMIT 50
");
while ($row = $result->fetch_assoc()) $notifs[] = $row;

// ── COUNT ─────────────────────────────────────────────────────────────────
$counts = ['semua'=>0,'unread'=>0,'info'=>0,'warning'=>0,'success'=>0];
$rC = $koneksi->query("SELECT tipe, is_read, COUNT(*) as c FROM notifikasi WHERE user_id=$user_id GROUP BY tipe, is_read");
while ($r = $rC->fetch_assoc()) {
    $counts['semua'] += $r['c'];
    if (!$r['is_read']) $counts['unread'] += $r['c'];
    if (isset($counts[$r['tipe']])) $counts[$r['tipe']] += $r['c'];
}
$unread = $counts['unread'];

// ── HELPERS ───────────────────────────────────────────────────────────────
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');

$rUser = $koneksi->query("SELECT nama FROM users WHERE id=$user_id");
$user  = $rUser->fetch_assoc();

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'Baru saja';
    if ($diff < 3600)     return floor($diff/60) . ' menit lalu';
    if ($diff < 86400)    return floor($diff/3600) . ' jam lalu';
    if ($diff < 604800)   return floor($diff/86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}

function notifIcon($tipe, $judul) {
    if (strpos($judul, 'Segera Dimulai') !== false || strpos($judul, 'Dimulai') !== false) return ['⏰','yellow'];
    if (strpos($judul, 'Dibatalkan')  !== false) return ['🚫','red'];
    if (strpos($judul, 'Dikonfirmasi')!== false) return ['✅','green'];
    if (strpos($judul, 'Ditolak')     !== false) return ['❌','red'];
    if (strpos($judul, 'Reschedule')  !== false) return ['🔄','blue'];
    return match($tipe) {
        'success' => ['✅','green'],
        'warning' => ['⚠️','yellow'],
        'info'    => ['📢','blue'],
        default   => ['🔔','gray'],
    };
}
$colorMap = [
    'green'  => ['bg'=>'rgba(34,197,94,.1)',   'border'=>'rgba(34,197,94,.25)',  'text'=>'#22c55e',  'icon_bg'=>'rgba(34,197,94,.15)'],
    'yellow' => ['bg'=>'rgba(245,158,11,.08)', 'border'=>'rgba(245,158,11,.25)', 'text'=>'#f59e0b',  'icon_bg'=>'rgba(245,158,11,.15)'],
    'red'    => ['bg'=>'rgba(239,68,68,.08)',  'border'=>'rgba(239,68,68,.2)',   'text'=>'#ef4444',  'icon_bg'=>'rgba(239,68,68,.12)'],
    'blue'   => ['bg'=>'rgba(59,130,246,.08)', 'border'=>'rgba(59,130,246,.2)',  'text'=>'#3b82f6',  'icon_bg'=>'rgba(59,130,246,.12)'],
    'gray'   => ['bg'=>'rgba(255,255,255,.03)','border'=>'rgba(255,255,255,.08)','text'=>'#9ca3af',  'icon_bg'=>'rgba(255,255,255,.06)'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartFutsal — Notifikasi</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif'],display:['Bebas Neue','sans-serif']},colors:{brand:{red:'#e8192c',red2:'#ff3344'}}}}}</script>
  <style>
    body{background:#0a0a0a;color:#f0f0f0}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:#2a2a2a;border-radius:4px}
    .nav-link{transition:all .15s;padding-bottom:2px}
    .nav-link.active{color:#ff3344;border-bottom:2px solid #e8192c}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp .3s ease forwards}
    .notif-card{transition:all .2s;cursor:pointer}
    .notif-card:hover{filter:brightness(1.08)}
    .notif-unread{box-shadow:inset 3px 0 0 #e8192c}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
    .pulse-dot{animation:pulse 2s infinite}
  </style>
</head>
<body class="font-sans min-h-screen">

<!-- NAVBAR -->
<nav class="sticky top-0 z-50 border-b border-white/5" style="background:#111">
  <div class="max-w-3xl mx-auto px-5 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-brand-red flex items-center justify-center">⚽</div>
      <span class="font-display text-lg tracking-wider">Smart<span class="text-brand-red">Futsal</span></span>
    </div>
    <div class="flex items-center gap-6">
      <a href="booking.php"    class="nav-link text-sm text-gray-400 no-underline">Booking</a>
      <a href="riwayat.php"    class="nav-link text-sm text-gray-400 no-underline">Riwayat</a>
      <a href="notifikasi.php" class="nav-link active text-sm font-semibold no-underline text-white relative">
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
<div style="background:linear-gradient(180deg,#0a0d1a 0%,#0a0a0a 100%)">
  <div class="max-w-3xl mx-auto px-5 py-10 fade-up">
    <div class="text-xs text-blue-400 tracking-widest uppercase font-semibold mb-2">🔔 Pusat Notifikasi</div>
    <div class="flex items-end justify-between">
      <div>
        <h1 class="font-display text-5xl tracking-widest mb-2">NOTIFI<span class="text-blue-400">KASI</span></h1>
        <p class="text-gray-500 text-sm">Pantau status booking dan pengingat jadwal mainmu.</p>
      </div>
      <?php if($unread>0):?>
      <a href="?mark_all=1" class="mb-1 text-xs px-4 py-2 rounded-xl border border-white/10 text-gray-400 hover:bg-white/5 transition no-underline">
        ✓ Tandai Semua Dibaca
      </a>
      <?php endif;?>
    </div>
  </div>
</div>

<div class="max-w-3xl mx-auto px-5 pb-16 mt-2">

  <!-- STAT BADGES -->
  <div class="flex gap-3 mb-5 flex-wrap fade-up">
    <?php
    $tabs = [
        'semua'   => ['label'=>'Semua',    'cnt'=>$counts['semua'],   'color'=>'text-gray-300'],
        'unread'  => ['label'=>'Belum Dibaca','cnt'=>$counts['unread'],'color'=>'text-brand-red'],
        'success' => ['label'=>'✅ Konfirmasi','cnt'=>$counts['success'],'color'=>'text-green-400'],
        'warning' => ['label'=>'⚠ Peringatan','cnt'=>$counts['warning'],'color'=>'text-yellow-400'],
        'info'    => ['label'=>'📢 Info',   'cnt'=>$counts['info'],    'color'=>'text-blue-400'],
    ];
    foreach($tabs as $k=>$t):
      $active = $filter===$k;
    ?>
    <a href="?filter=<?=$k?>" class="no-underline text-xs px-4 py-2 rounded-xl border transition
       <?=$active?'bg-white/10 border-white/20 text-white font-semibold':'border-white/5 text-gray-500 hover:bg-white/5'?>">
      <?=$t['label']?>
      <span class="ml-1 <?=$t['color']?> font-bold">(<?=$t['cnt']?>)</span>
    </a>
    <?php endforeach;?>
  </div>

  <!-- NOTIFIKASI LIST -->
  <?php if(empty($notifs)):?>
  <div class="rounded-2xl border border-white/5 p-12 text-center fade-up" style="background:#161616">
    <div class="text-5xl mb-4">🔕</div>
    <div class="text-gray-500 text-sm mb-1">Tidak ada notifikasi<?=$filter!=='semua'?' dengan filter ini':''?>.</div>
    <div class="text-gray-600 text-xs">Notifikasi akan muncul saat ada update booking.</div>
  </div>
  <?php else:?>
  <div class="space-y-3">
    <?php
    $lastDate = '';
    foreach($notifs as $i=>$n):
      [$icon, $color] = notifIcon($n['tipe'], $n['judul']);
      $c = $colorMap[$color];
      $date_label = date('Y-m-d', strtotime($n['sent_at']));
      $date_human = '';
      if($date_label === date('Y-m-d')) $date_human = 'Hari Ini';
      elseif($date_label === date('Y-m-d',strtotime('-1 day'))) $date_human = 'Kemarin';
      else $date_human = date('d M Y', strtotime($n['sent_at']));
      $showDate = $date_human !== $lastDate;
      $lastDate = $date_human;
    ?>

    <?php if($showDate):?>
    <div class="text-xs text-gray-600 font-semibold tracking-wider uppercase pt-<?=$i>0?'4':'0'?> pb-1">
      <?=$date_human?>
    </div>
    <?php endif;?>

<a href="?read=<?=$n['id']?>" class="no-underline block">
<div class="notif-card rounded-2xl border overflow-hidden <?=!$n['is_read']?'notif-unread':''?>"
     style="background:<?=$c['bg']?>;border-color:<?=$c['border']?>">

  <div class="p-4">

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <div class="text-xl"><?=$icon?></div>
        <div class="font-bold text-sm text-white">
          <?=htmlspecialchars($n['judul'])?>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <?php if(!$n['is_read']): ?>
          <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
        <?php endif; ?>
        <span class="text-xs text-gray-500"><?=timeAgo($n['sent_at'])?></span>
      </div>
    </div>

    <!-- ISI (MIRIP RIWAYAT) -->
    <div class="space-y-1">

      <!-- Nama Lapangan -->
      <div class="font-semibold text-white text-sm">
        🏟 <?=htmlspecialchars($n['nama_lapangan'])?>
      </div>

      <!-- Tanggal & Jam -->
      <div class="text-xs text-gray-400">
        📅 <?=date('d M Y', strtotime($n['tanggal']))?>
        · ⏰ <?=substr($n['jam_mulai'],0,5)?> - <?=substr($n['jam_selesai'],0,5)?>
      </div>

      <!-- Kode Booking -->
      <div class="text-xs text-gray-500">
        Kode: <span class="font-mono"><?=htmlspecialchars($n['kode_booking'])?></span>
      </div>

      <!-- Pesan -->
      <div class="mt-2 text-sm" style="color:<?=$c['text']?>;">
        <?=htmlspecialchars($n['pesan'])?>
      </div>

    </div>

    <!-- STATUS -->
    <div class="mt-3 flex items-center justify-between">

      <span class="text-xs px-2 py-1 rounded-lg
        <?php
          if($n['tipe']=='success') echo 'bg-green-500/20 text-green-400';
          elseif($n['tipe']=='warning') echo 'bg-yellow-500/20 text-yellow-400';
          elseif($n['tipe']=='info') echo 'bg-blue-500/20 text-blue-400';
          else echo 'bg-gray-500/20 text-gray-400';
        ?>">
        <?=strtoupper($n['tipe'])?>
      </span>

      <!-- Tombol -->
      <a href="riwayat.php"
         class="text-xs px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 transition">
         Lihat Detail
      </a>

    </div>

  </div>
</div>
</a>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- Empty state bawah -->
  <?php if(count($notifs) >= 50):?>
  <div class="text-center text-xs text-gray-600 mt-8">Menampilkan 50 notifikasi terbaru.</div>
  <?php endif;?>

</div>
<script>
setInterval(() => {
  location.reload();
}, 5000);
</script>
</body>
</html>