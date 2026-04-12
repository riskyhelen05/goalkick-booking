<?php
session_start();
include '../koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: login.php'); exit;
}
$user_id = intval($_SESSION['user_id']);

// ── CONFIG ────────────────────────────────────────────────────────────────
define('DP_PERSEN', 50);

$rekening_data = [
    'BRI'   => ['no'=>'1234-5678-9012-3456','atas'=>'SmartFutsal FC','metode'=>'transfer','logo'=>'🏦','color'=>'#1a6bc9','bg'=>'rgba(26,107,201,.12)'],
    'BNI'   => ['no'=>'8765-4321-0000-1111','atas'=>'SmartFutsal FC','metode'=>'transfer','logo'=>'🏦','color'=>'#f77f00','bg'=>'rgba(247,127,0,.1)'],
    'BCA'   => ['no'=>'0123-4567-8900-1122','atas'=>'SmartFutsal FC','metode'=>'transfer','logo'=>'🏦','color'=>'#00529c','bg'=>'rgba(0,82,156,.12)'],
    'GoPay' => ['no'=>'0812-3456-7890',      'atas'=>'SmartFutsal FC','metode'=>'e-wallet','logo'=>'📱','color'=>'#00aed6','bg'=>'rgba(0,174,214,.1)'],
    'OVO'   => ['no'=>'0812-3456-7890',      'atas'=>'SmartFutsal FC','metode'=>'e-wallet','logo'=>'📱','color'=>'#4c3494','bg'=>'rgba(76,52,148,.12)'],
];

// ── UPLOAD BUKTI ──────────────────────────────────────────────────────────
function uploadBukti($file) {
    $dir = '../uploads/bukti/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return ['ok'=>false,'msg'=>'Format tidak didukung. Gunakan JPG/PNG/WEBP.'];
    if ($file['size'] > 5*1024*1024)      return ['ok'=>false,'msg'=>'Ukuran file max 5MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Error upload file.'];
    $fn = 'bukti_'.uniqid().'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fn)) return ['ok'=>false,'msg'=>'Gagal menyimpan file.'];
    return ['ok'=>true,'url'=>$fn];
}

// ── HANDLE SUBMIT ─────────────────────────────────────────────────────────
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='booking') {
    $lap_id      = intval($_POST['lapangan_id']??0);
    $tanggal     = $koneksi->real_escape_string($_POST['tanggal']??'');
    $jam_mulai   = $koneksi->real_escape_string($_POST['jam_mulai']??'');
    $durasi      = intval($_POST['durasi']??1);
    $metode_bank = trim($_POST['metode_bank']??'');
    $tipe_bayar  = in_array($_POST['tipe_bayar']??'',['penuh','dp']) ? $_POST['tipe_bayar'] : 'penuh';

    if (!$lap_id||!$tanggal||!$jam_mulai||$durasi<1||!$metode_bank) {
        $error = 'Semua field wajib diisi termasuk metode pembayaran.';
    } elseif ($tanggal < date('Y-m-d')) {
        $error = 'Tanggal tidak boleh di masa lalu.';
    } elseif (!isset($rekening_data[$metode_bank])) {
        $error = 'Metode pembayaran tidak valid.';
    } else {
        $jam_sel = date('H:i:s', strtotime("$tanggal $jam_mulai") + $durasi*3600);
        if ($jam_sel > '22:00:00') {
            $error = 'Jam selesai melebihi jam operasional (22:00).';
        } else {
            $rL = $koneksi->query("SELECT harga_per_jam FROM lapangan WHERE id=$lap_id AND status='tersedia'");
            if ($rL->num_rows===0) {
                $error = 'Lapangan tidak tersedia.';
            } else {
                $harga     = intval($rL->fetch_assoc()['harga_per_jam']);
                $total     = $harga * $durasi;
                $jml_bayar = $tipe_bayar==='dp' ? intval(ceil($total*DP_PERSEN/100)) : $total;
                $sisa      = $total - $jml_bayar;

                $bukti_url = null;
                if (!empty($_FILES['bukti_bayar']['name'])) {
                    $up = uploadBukti($_FILES['bukti_bayar']);
                    if (!$up['ok']) { $error = $up['msg']; }
                    else $bukti_url = $koneksi->real_escape_string($up['url']);
                }

                if (!$error) {
                    $kode = 'BK'.strtoupper(substr(md5(uniqid()),0,8));
                    $sql  = "INSERT INTO booking (kode_booking,user_id,lapangan_id,tanggal,jam_mulai,jam_selesai,durasi_jam,harga_saat_booking,total_harga,status)
                             VALUES ('$kode',$user_id,$lap_id,'$tanggal','$jam_mulai','$jam_sel',$durasi,$harga,$total,'pending')";
                    if ($koneksi->query($sql)) {
                        $bid     = $koneksi->insert_id;
                        $bsql    = $bukti_url ? "'$bukti_url'" : 'NULL';
                        $metode  = $koneksi->real_escape_string($rekening_data[$metode_bank]['metode']);
                        $koneksi->query("INSERT INTO pembayaran (booking_id,metode,jumlah,status,bukti_url) VALUES ($bid,'$metode',$jml_bayar,'pending',$bsql)");
                        $mbesc   = $koneksi->real_escape_string($metode_bank);
                        $tl      = $tipe_bayar==='dp' ? "DP ".DP_PERSEN."% (Rp ".number_format($jml_bayar,0,',','.').")" : "Lunas";
                        $sm      = $sisa>0 ? " Sisa Rp ".number_format($sisa,0,',','.')." dibayar saat main." : "";
                        $koneksi->query("INSERT INTO notifikasi (user_id,booking_id,judul,pesan,tipe) VALUES ($user_id,$bid,'Booking Berhasil Dibuat','Booking $kode diterima. Pembayaran $tl via $mbesc.$sm Tunggu konfirmasi admin.','info')");
                        $sh  = $sisa>0 ? " &nbsp;·&nbsp; Sisa <strong>Rp ".number_format($sisa,0,',','.')."</strong> dibayar saat tiba." : '';
                        $success = "🎉 Booking berhasil! Kode: <strong>$kode</strong>$sh";
                    } else {
                        $error = 'Jadwal sudah terisi atau lapangan diblokir. Pilih waktu lain.';
                    }
                }
            }
        }
    }
}

// ── QUERY DATA ────────────────────────────────────────────────────────────
$lapangans = [];
$rL = $koneksi->query("SELECT * FROM lapangan WHERE status='tersedia' ORDER BY harga_per_jam ASC");
while ($r=$rL->fetch_assoc()) $lapangans[]=$r;

$tgl_cek = $_GET['tanggal'] ?? date('Y-m-d');
$lap_cek = intval($_GET['lapangan_id'] ?? 0);
$booked  = [];
if ($lap_cek && $tgl_cek) {
    $e = $koneksi->real_escape_string($tgl_cek);
    $r = $koneksi->query("SELECT jam_mulai,jam_selesai FROM booking WHERE lapangan_id=$lap_cek AND tanggal='$e' AND status IN('pending','dikonfirmasi')");
    while($row=$r->fetch_assoc()) $booked[]=$row;
    $r = $koneksi->query("SELECT jam_mulai,jam_selesai FROM jadwal_blokir WHERE lapangan_id=$lap_cek AND tanggal='$e'");
    while($row=$r->fetch_assoc()) $booked[]=$row;
}

function rupiah($n){return 'Rp '.number_format($n,0,',','.');}
function isBooked($jam,$slots){$ts=strtotime($jam);foreach($slots as $s)if($ts>=strtotime($s['jam_mulai'])&&$ts<strtotime($s['jam_selesai']))return true;return false;}

$rUser  = $koneksi->query("SELECT nama FROM users WHERE id=$user_id");
$user   = $rUser->fetch_assoc();
$rNotif = $koneksi->query("SELECT COUNT(*) as c FROM notifikasi WHERE user_id=$user_id AND is_read=0");
$unread = $rNotif->fetch_assoc()['c'];
$jam_ops    = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00'];
$jenis_icon = ['sintetis'=>'⚽','vinyl'=>'🏟️','rumput'=>'🌿'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>SmartFutsal — Booking Lapangan</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif'],display:['Bebas Neue','sans-serif']},colors:{brand:{red:'#e8192c',red2:'#ff3344'}}}}}</script>
  <style>
    body{background:#0a0a0a;color:#f0f0f0}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:#2a2a2a;border-radius:4px}
    .nav-link{transition:all .15s;padding-bottom:2px}
    .nav-link.active{color:#ff3344;border-bottom:2px solid #e8192c}
    .card{background:#161616;border:1px solid rgba(255,255,255,0.06)}
    .slot-available{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#22c55e;cursor:pointer;transition:all .15s}
    .slot-available:hover,.slot-available.selected{background:rgba(34,197,94,.25);border-color:#22c55e}
    .slot-booked{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#6b7280;cursor:not-allowed}
    .slot-past{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);color:#374151;cursor:not-allowed}
    .lap-card{background:#161616;border:2px solid rgba(255,255,255,.06);transition:all .2s;cursor:pointer}
    .lap-card:hover{border-color:rgba(232,25,44,.4)}
    .lap-card.selected{border-color:#e8192c;background:rgba(232,25,44,.06)}
    .metode-btn{background:#1a1a1a;border:2px solid rgba(255,255,255,.07);transition:all .2s;cursor:pointer;border-radius:.75rem}
    .metode-btn:hover{border-color:rgba(255,255,255,.2)}
    .metode-btn.sel{border-color:var(--mc)!important;background:var(--mb)!important}
    .tipe-card{border:2px solid rgba(255,255,255,.07);background:#1a1a1a;transition:all .2s;cursor:pointer;border-radius:.75rem}
    .tipe-card:hover{border-color:rgba(255,255,255,.15)}
    .tipe-card.sel-full{border-color:#22c55e!important;background:rgba(34,197,94,.07)!important}
    .tipe-card.sel-dp{border-color:#f59e0b!important;background:rgba(245,158,11,.07)!important}
    .upload-zone{border:2px dashed rgba(255,255,255,.1);transition:all .2s;cursor:pointer;border-radius:.75rem}
    .upload-zone:hover,.upload-zone.drag{border-color:rgba(232,25,44,.5);background:rgba(232,25,44,.03)}
    .upload-zone.done{border-style:solid;border-color:#22c55e;background:rgba(34,197,94,.05)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp .3s ease}
    @keyframes slideIn{from{opacity:0;max-height:0;transform:translateY(-6px)}to{opacity:1;max-height:300px;transform:translateY(0)}}
    .slide-in{animation:slideIn .22s ease forwards;overflow:hidden}
    input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);cursor:pointer}
    input[type="file"]{display:none}
    .chk-row{display:flex;align-items:center;gap:.5rem;font-size:.75rem;color:#6b7280;transition:color .2s}
    .chk-row.done{color:#22c55e}
    .chk-dot{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.12);flex-shrink:0;transition:all .2s}
    .chk-row.done .chk-dot{background:#22c55e;border-color:#22c55e}
  </style>
</head>
<body class="font-sans min-h-screen">

<!-- ═══ NAVBAR ═══ -->
<nav class="sticky top-0 z-50 border-b border-white/5" style="background:#111">
  <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-brand-red flex items-center justify-center">⚽</div>
      <span class="font-display text-lg tracking-wider">Smart<span class="text-brand-red">Futsal</span></span>
    </div>
    <div class="flex items-center gap-6">
      <a href="booking.php"    class="nav-link active text-sm font-semibold no-underline text-white">Booking</a>
      <a href="riwayat.php"    class="nav-link text-sm text-gray-400 no-underline">Riwayat</a>
      <a href="notifikasi.php" class="nav-link text-sm text-gray-400 no-underline relative">
        Notifikasi<?php if($unread>0):?><span class="absolute -top-2 -right-3 w-4 h-4 bg-brand-red rounded-full text-xs flex items-center justify-center font-bold"><?=$unread?></span><?php endif;?>
      </a>
      <div class="flex items-center gap-2 pl-4 border-l border-white/10">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#e8192c,#800010)"><?=strtoupper(substr($user['nama']??'U',0,1))?></div>
        <span class="text-sm text-gray-300"><?=htmlspecialchars($user['nama']??'')?></span>
        <a href="logout.php" class="ml-2 text-xs text-gray-600 hover:text-red-400 no-underline transition">Keluar</a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<div style="background:linear-gradient(180deg,#1a0808 0%,#0a0a0a 100%)">
  <div class="max-w-6xl mx-auto px-5 py-10 fade-up">
    <div class="text-xs text-brand-red tracking-widest uppercase font-semibold mb-2">⚽ Reservasi Sekarang</div>
    <h1 class="font-display text-5xl tracking-widest mb-2">BOOKING <span class="text-brand-red">LAPANGAN</span></h1>
    <p class="text-gray-500 text-sm">Pilih lapangan, tanggal, jam, pilih metode bayar, dan selesaikan pembayaranmu.</p>
  </div>
</div>

<div class="max-w-6xl mx-auto px-5 pb-20 mt-2">

  <?php if($error):?>
  <div class="mb-5 px-4 py-3 rounded-xl text-sm font-semibold bg-red-500/10 text-red-400 border border-red-500/20 fade-up">❌ <?=htmlspecialchars($error)?></div>
  <?php endif;?>
  <?php if($success):?>
  <div class="mb-5 px-4 py-3 rounded-xl text-sm bg-green-500/10 text-green-400 border border-green-500/20 fade-up">
    <?=$success?> <a href="riwayat.php" class="ml-2 underline text-green-300 font-semibold no-underline">Lihat riwayat →</a>
  </div>
  <?php endif;?>

  <div class="grid gap-6" style="grid-template-columns:1fr 390px">

    <!-- ═══ KIRI ═══ -->
    <div class="space-y-5">

      <!-- STEP 1: LAPANGAN -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">1</div>
          <span class="font-semibold text-sm">Pilih Lapangan</span>
        </div>
        <?php if(empty($lapangans)):?>
        <p class="text-center text-gray-600 text-sm py-4">Tidak ada lapangan tersedia saat ini.</p>
        <?php else:?>
        <div class="grid grid-cols-2 gap-3">
          <?php foreach($lapangans as $l):?>
          <div class="lap-card rounded-xl p-4 <?=$l['id']==$lap_cek?'selected':''?>"
               onclick="selectLapangan(<?=$l['id']?>,'<?=addslashes(htmlspecialchars($l['nama']))?>', <?=$l['harga_per_jam']?>)">
            <div class="text-2xl mb-2"><?=$jenis_icon[$l['jenis']]??'🏟️'?></div>
            <div class="font-bold text-sm"><?=htmlspecialchars($l['nama'])?></div>
            <div class="text-xs text-gray-500 capitalize mt-0.5"><?=$l['jenis']?></div>
            <div class="text-xs text-brand-red font-bold mt-2"><?=rupiah($l['harga_per_jam'])?>/jam</div>
            <?php if($l['fasilitas']):?><div class="text-xs text-gray-600 mt-1 truncate"><?=htmlspecialchars($l['fasilitas'])?></div><?php endif;?>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
      </div>

      <!-- STEP 2: TANGGAL -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">2</div>
          <span class="font-semibold text-sm">Pilih Tanggal</span>
        </div>
        <div class="flex gap-3">
          <input type="date" id="tanggalInput" value="<?=htmlspecialchars($tgl_cek)?>" min="<?=date('Y-m-d')?>"
                 class="flex-1 rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a"
                 onchange="refreshJadwal()"/>
          <button onclick="refreshJadwal()" class="px-5 py-3 rounded-xl bg-brand-red text-white text-sm font-semibold hover:opacity-90 transition">Cek Jadwal</button>
        </div>
      </div>

      <!-- STEP 3: JAM -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">3</div>
            <span class="font-semibold text-sm">Pilih Jam Mulai</span>
          </div>
          <div class="flex gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.4)"></span>Tersedia</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)"></span>Terisi</span>
          </div>
        </div>
        <?php if(!$lap_cek):?>
        <div class="text-center py-8 text-gray-600 text-sm">← Pilih lapangan dan tanggal terlebih dahulu.</div>
        <?php else:?>
        <div class="grid grid-cols-4 gap-2">
          <?php $now_ts=time(); foreach($jam_ops as $jam):
            $st   = strtotime("$tgl_cek $jam");
            $past = ($tgl_cek===date('Y-m-d') && $st<=$now_ts);
            $bkd  = isBooked($jam,$booked);
            $cls  = $past?'slot-past':($bkd?'slot-booked':'slot-available');
          ?>
          <div class="<?=$cls?> rounded-xl px-3 py-3 text-center text-xs font-semibold"
               <?=(!$past&&!$bkd)?"onclick=\"selectSlot(this,'$jam')\"":''?>>
            <div class="text-sm font-bold"><?=$jam?></div>
            <div class="opacity-60 mt-0.5"><?=$bkd?'Terisi':($past?'Lewat':'Tersedia')?></div>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
      </div>

      <!-- STEP 4: PEMBAYARAN -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center gap-2 mb-5">
          <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">4</div>
          <span class="font-semibold text-sm">Pembayaran</span>
        </div>

        <!-- 4A: Tipe Bayar -->
        <div class="mb-6">
          <div class="text-xs text-gray-500 mb-3 font-semibold uppercase tracking-wide">Tipe Pembayaran</div>
          <div class="grid grid-cols-2 gap-3">
            <div class="tipe-card sel-full p-4" id="tipe_penuh" onclick="selectTipe('penuh')">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-4 h-4 rounded-full border-2 border-green-500 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-green-500" id="dot_penuh"></div>
                </div>
                <span class="text-sm font-bold text-green-400">Bayar Penuh</span>
              </div>
              <p class="text-xs text-gray-500 mb-1.5">Bayar 100% sekarang, langsung dikonfirmasi.</p>
              <div id="lbl_penuh" class="text-xs text-green-400 font-bold">— pilih lapangan & durasi —</div>
            </div>
            <div class="tipe-card p-4" id="tipe_dp" onclick="selectTipe('dp')">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-4 h-4 rounded-full border-2 border-white/20 flex items-center justify-center" id="radio_dp">
                  <div class="w-2 h-2 rounded-full" id="dot_dp" style="background:transparent"></div>
                </div>
                <span class="text-sm font-bold text-yellow-400">DP <?=DP_PERSEN?>%</span>
              </div>
              <p class="text-xs text-gray-500 mb-1.5">Bayar <?=DP_PERSEN?>% sekarang, sisa saat main.</p>
              <div id="lbl_dp" class="text-xs text-yellow-400 font-bold">— pilih lapangan & durasi —</div>
            </div>
          </div>

          <!-- Info DP breakdown -->
          <div id="box_dp_info" class="hidden mt-3 rounded-xl p-4 border border-yellow-500/20 slide-in" style="background:rgba(245,158,11,.06)">
            <div class="text-xs font-bold text-yellow-400 mb-2">💰 Rincian Pembayaran DP</div>
            <div class="grid grid-cols-2 gap-4">
              <div class="rounded-lg p-3" style="background:rgba(245,158,11,.1)">
                <div class="text-xs text-gray-500 mb-0.5">Bayar sekarang (DP)</div>
                <div id="dp_now" class="text-base font-bold text-yellow-300">—</div>
              </div>
              <div class="rounded-lg p-3" style="background:rgba(255,255,255,.04)">
                <div class="text-xs text-gray-500 mb-0.5">Bayar saat tiba di lapangan</div>
                <div id="dp_nanti" class="text-base font-bold text-white">—</div>
              </div>
            </div>
            <div class="text-xs text-gray-600 mt-2">⚠ Booking bisa dibatalkan jika sisa tidak dibayar saat main.</div>
          </div>
        </div>

        <!-- 4B: Metode Pembayaran -->
        <div class="mb-6">
          <div class="text-xs text-gray-500 mb-3 font-semibold uppercase tracking-wide">Pilih Metode Pembayaran</div>
          <div class="grid grid-cols-5 gap-2 mb-3">
            <?php foreach($rekening_data as $key=>$rek):?>
            <div class="metode-btn p-3 text-center" id="mb_<?=$key?>"
                 style="--mc:<?=$rek['color']?>;--mb:<?=$rek['bg']?>"
                 onclick="selectMetode('<?=$key?>')">
              <div class="text-xl mb-1"><?=$rek['logo']?></div>
              <div class="text-xs font-bold" style="color:<?=$rek['color']?>"><?=$key?></div>
            </div>
            <?php endforeach;?>
          </div>

          <!-- Panel rekening per metode -->
          <?php foreach($rekening_data as $key=>$rek):?>
          <div id="panel_<?=$key?>" class="hidden rounded-xl overflow-hidden slide-in">
            <div class="p-4 rounded-xl" style="background:<?=$rek['bg']?>;border:1px solid <?=$rek['color']?>44">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span class="text-xl"><?=$rek['logo']?></span>
                  <span class="font-bold" style="color:<?=$rek['color']?>"><?=$key?></span>
                  <span class="text-xs text-gray-500 px-2 py-0.5 rounded-full" style="background:rgba(0,0,0,.3)"><?=$rek['metode']==='e-wallet'?'e-Wallet':'Bank Transfer'?></span>
                </div>
                <span class="text-xs px-2.5 py-1 rounded-full text-white font-bold" style="background:<?=$rek['color']?>">✓ Dipilih</span>
              </div>
              <!-- Nomor -->
              <div class="rounded-xl p-3 mb-2" style="background:rgba(0,0,0,.35)">
                <div class="text-xs text-gray-400 mb-1.5"><?=$rek['metode']==='e-wallet'?'Nomor HP / Akun':'Nomor Rekening'?></div>
                <div class="flex items-center justify-between">
                  <span class="font-mono font-bold text-xl text-white tracking-widest"><?=$rek['no']?></span>
                  <button type="button" onclick="copyNo('<?=str_replace('-','',$rek['no'])?>',this)"
                          class="text-xs px-3 py-1.5 rounded-lg font-semibold transition-all"
                          style="background:<?=$rek['color']?>22;color:<?=$rek['color']?>;border:1px solid <?=$rek['color']?>44">
                    Salin
                  </button>
                </div>
                <div class="text-xs text-gray-500 mt-1.5">a.n &nbsp;<span class="text-white font-semibold"><?=$rek['atas']?></span></div>
              </div>
              <!-- Jumlah yang harus ditransfer -->
              <div class="rounded-xl p-3 flex items-center justify-between" style="background:rgba(0,0,0,.2)">
                <span class="text-xs text-gray-400">Transfer sebesar</span>
                <span id="tr_amount_<?=$key?>" class="font-bold text-white" style="color:<?=$rek['color']?>">— Pilih durasi —</span>
              </div>
              <div class="text-xs text-gray-500 mt-2.5">💡 Transfer <em>sesuai nominal</em> di atas, lalu upload bukti di bawah.</div>
            </div>
          </div>
          <?php endforeach;?>
        </div>

        <!-- 4C: Upload Bukti -->
        <div>
          <div class="text-xs text-gray-500 mb-2 font-semibold uppercase tracking-wide">Upload Bukti Transfer</div>
          <div class="upload-zone p-6 text-center" id="dropZone"
               onclick="document.getElementById('buktiInput').click()"
               ondragover="event.preventDefault();this.classList.add('drag')"
               ondragleave="this.classList.remove('drag')"
               ondrop="handleDrop(event)">
            <div id="ph">
              <div class="text-4xl mb-3">📸</div>
              <div class="text-sm font-semibold text-gray-400">Klik atau drag foto bukti transfer</div>
              <div class="text-xs text-gray-600 mt-1">JPG, PNG, WEBP · Max 5MB</div>
            </div>
            <div id="prev" class="hidden">
              <img id="prevImg" src="" class="max-h-44 mx-auto rounded-xl object-contain mb-3"/>
              <div id="prevName" class="text-xs text-green-400 font-semibold mb-1"></div>
              <button type="button" onclick="event.stopPropagation();clearFile()"
                      class="text-xs text-red-400 hover:underline">✕ Hapus foto</button>
            </div>
          </div>
          <p class="text-xs text-gray-600 mt-1.5">Bisa upload sekarang atau kirim manual ke admin. Booking tetap masuk sebagai <em>pending</em>.</p>
        </div>

      </div><!-- end step 4 -->
    </div><!-- end kiri -->

    <!-- ═══ KANAN: FORM + RINGKASAN ═══ -->
    <div>
      <form method="POST" enctype="multipart/form-data" id="bookingForm">
        <input type="hidden" name="action"      value="booking">
        <input type="hidden" name="lapangan_id" id="f_lap"    value="<?=$lap_cek?>">
        <input type="hidden" name="tanggal"     id="f_tgl"    value="<?=htmlspecialchars($tgl_cek)?>">
        <input type="hidden" name="jam_mulai"   id="f_jam"    value="">
        <input type="hidden" name="metode_bank" id="f_metode" value="">
        <input type="hidden" name="tipe_bayar"  id="f_tipe"   value="penuh">
        <input type="file"   name="bukti_bayar" id="buktiInput" accept="image/*" onchange="handleFile(this)">

        <div class="card rounded-2xl p-5 fade-up sticky top-24">
          <div class="font-display text-xl tracking-wider mb-5">RINGKASAN <span class="text-brand-red">BOOKING</span></div>

          <!-- Lapangan -->
          <div class="rounded-xl p-4 mb-3" style="background:#1a1a1a">
            <div class="text-xs text-gray-500 mb-1">Lapangan</div>
            <div id="sum_lap" class="font-bold text-white">— Belum dipilih —</div>
          </div>

          <!-- Tanggal & Jam -->
          <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="rounded-xl p-3" style="background:#1a1a1a">
              <div class="text-xs text-gray-500 mb-1">Tanggal</div>
              <div id="sum_tgl" class="font-bold text-xs text-white"><?=date('d M Y',strtotime($tgl_cek))?></div>
            </div>
            <div class="rounded-xl p-3" style="background:#1a1a1a">
              <div class="text-xs text-gray-500 mb-1">Jam Mulai</div>
              <div id="sum_jam" class="font-bold text-xs text-brand-red">— Pilih jam —</div>
            </div>
          </div>

          <!-- Durasi -->
          <div class="mb-4">
            <label class="text-xs text-gray-500 block mb-1.5">Durasi Main</label>
            <select name="durasi" id="durasiSel" onchange="updateCalc()"
                    class="w-full rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
              <option value="1">1 Jam</option>
              <option value="2" selected>2 Jam</option>
              <option value="3">3 Jam</option>
              <option value="4">4 Jam</option>
            </select>
          </div>

          <!-- Harga breakdown -->
          <div class="rounded-xl p-4 mb-3 border border-white/5" style="background:#111">
            <div class="flex justify-between text-xs mb-2">
              <span class="text-gray-500">Total booking</span>
              <span id="s_total" class="text-white font-semibold">—</span>
            </div>
            <div id="row_sisa" class="hidden flex justify-between text-xs mb-2">
              <span class="text-gray-500">Dibayar saat main</span>
              <span id="s_sisa" class="text-gray-400 font-semibold">—</span>
            </div>
            <div class="border-t border-white/8 pt-2.5 flex justify-between items-center">
              <span class="text-xs text-gray-400" id="s_bayar_label">Bayar sekarang (Lunas)</span>
              <span id="s_bayar" class="font-display text-2xl text-brand-red tracking-wide">Rp —</span>
            </div>
          </div>

          <!-- Metode badge -->
          <div id="s_metode_box" class="hidden rounded-xl px-4 py-3 mb-3 flex items-center gap-3" style="background:#1a1a1a">
            <span id="s_metode_logo" class="text-xl">💳</span>
            <div>
              <div id="s_metode_name" class="text-xs font-bold text-white"></div>
              <div id="s_metode_no"   class="text-xs font-mono text-gray-500"></div>
            </div>
          </div>

          <!-- Bukti badge -->
          <div id="s_bukti_box" class="hidden rounded-xl px-4 py-2.5 mb-4 flex items-center gap-2 border border-green-500/20" style="background:rgba(34,197,94,.06)">
            <span>📎</span>
            <div>
              <div class="text-xs font-semibold text-green-400">Bukti pembayaran siap diupload</div>
              <div id="s_bukti_name" class="text-xs text-gray-500 truncate max-w-[200px]"></div>
            </div>
          </div>

          <!-- Checklist -->
          <div class="rounded-xl p-3.5 mb-5 border border-white/5" style="background:#111">
            <div class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-2.5">Progress</div>
            <div class="space-y-2">
              <div class="chk-row" id="chk_lap"><div class="chk-dot"></div>Pilih lapangan</div>
              <div class="chk-row" id="chk_tgl"><div class="chk-dot"></div>Pilih tanggal</div>
              <div class="chk-row" id="chk_jam"><div class="chk-dot"></div>Pilih jam</div>
              <div class="chk-row" id="chk_met"><div class="chk-dot"></div>Pilih metode pembayaran</div>
            </div>
          </div>

          <button type="submit" id="btnOk"
                  class="w-full py-4 rounded-xl bg-brand-red text-white font-bold text-sm hover:opacity-90 transition"
                  disabled style="opacity:.35;cursor:not-allowed">
            ⚡ Konfirmasi Booking
          </button>
          <p class="text-xs text-gray-600 text-center mt-2">Status <em>pending</em> sampai admin verifikasi pembayaran.</p>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
// ═══ STATE ═══════════════════════════════════════════════════════════════
let sLap = {id:<?=$lap_cek?:0?>,nama:'',harga:0};
let sJam='', sMetode='', sTipe='penuh';

const REK = <?=json_encode($rekening_data)?>;

<?php foreach($lapangans as $l):?>
if(sLap.id===<?=$l['id']?>){sLap.nama='<?=addslashes(htmlspecialchars($l['nama']))?>'; sLap.harga=<?=$l['harga_per_jam']?>;}
<?php endforeach;?>
if(sLap.id>0) document.getElementById('sum_lap').textContent=sLap.nama;

// ═══ HELPERS ═════════════════════════════════════════════════════════════
const fmt = n => 'Rp '+ parseInt(n).toLocaleString('id-ID');
const dur = ()  => parseInt(document.getElementById('durasiSel').value)||2;
const totalHarga = () => sLap.harga * dur();
const jumlahBayar= () => {
  const t=totalHarga();
  return sTipe==='dp' ? Math.ceil(t*<?=DP_PERSEN?>/100) : t;
};

// ═══ UPDATE KALKULASI ════════════════════════════════════════════════════
function updateCalc(){
  const total=totalHarga(), bayar=jumlahBayar(), sisa=total-bayar;

  document.getElementById('s_total').textContent  = total ? fmt(total) : '—';
  document.getElementById('s_bayar').textContent  = total ? fmt(bayar) : 'Rp —';
  document.getElementById('s_bayar_label').textContent = sTipe==='dp' ? 'Bayar DP sekarang' : 'Bayar sekarang (Lunas)';

  const rowSisa = document.getElementById('row_sisa');
  if(sTipe==='dp' && total){
    rowSisa.classList.remove('hidden'); rowSisa.classList.add('flex');
    document.getElementById('s_sisa').textContent = fmt(sisa);
    document.getElementById('box_dp_info').classList.remove('hidden');
    document.getElementById('dp_now').textContent   = fmt(bayar);
    document.getElementById('dp_nanti').textContent = fmt(sisa);
  } else {
    rowSisa.classList.add('hidden'); rowSisa.classList.remove('flex');
    document.getElementById('box_dp_info').classList.add('hidden');
  }

  // Label di kartu tipe
  document.getElementById('lbl_penuh').textContent = total ? fmt(total) : '— pilih lapangan & durasi —';
  document.getElementById('lbl_dp').textContent    = total
    ? 'DP: '+fmt(Math.ceil(total*<?=DP_PERSEN?>/100))+' · Sisa: '+fmt(total-Math.ceil(total*<?=DP_PERSEN?>/100))
    : '— pilih lapangan & durasi —';

  // Update jumlah di panel rekening
  Object.keys(REK).forEach(k=>{
    const el=document.getElementById('tr_amount_'+k);
    if(el) el.textContent = total ? fmt(bayar) : '— Pilih durasi —';
  });

  checkReady();
}

// ═══ CHECKLIST ═══════════════════════════════════════════════════════════
function chk(id, ok){
  const r=document.getElementById('chk_'+id);
  if(ok) r.classList.add('done'); else r.classList.remove('done');
}
function updateChecklist(){
  chk('lap', sLap.id>0);
  chk('tgl', !!document.getElementById('f_tgl').value);
  chk('jam', !!sJam);
  chk('met', !!sMetode);
}

// ═══ READY CHECK ═════════════════════════════════════════════════════════
function checkReady(){
  const ok = sLap.id>0 && sJam && sMetode;
  const btn=document.getElementById('btnOk');
  btn.disabled=!ok; btn.style.opacity=ok?'1':'.35'; btn.style.cursor=ok?'pointer':'not-allowed';
  updateChecklist();
}

// ═══ SELECT LAPANGAN ══════════════════════════════════════════════════════
function selectLapangan(id,nama,harga){
  sLap={id,nama,harga};
  document.getElementById('f_lap').value=id;
  document.getElementById('sum_lap').textContent=nama;
  document.querySelectorAll('.lap-card').forEach(c=>c.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  updateCalc(); refreshJadwal();
}

// ═══ SELECT JAM ══════════════════════════════════════════════════════════
function selectSlot(el,jam){
  document.querySelectorAll('.slot-available').forEach(s=>s.classList.remove('selected'));
  el.classList.add('selected');
  sJam=jam;
  document.getElementById('f_jam').value=jam;
  document.getElementById('sum_jam').textContent=jam+' WIB';
  checkReady();
}

// ═══ SELECT TIPE ═════════════════════════════════════════════════════════
function selectTipe(t){
  sTipe=t;
  document.getElementById('f_tipe').value=t;
  const ep=document.getElementById('tipe_penuh'), ed=document.getElementById('tipe_dp');
  const dp=document.getElementById('dot_penuh'), dd=document.getElementById('dot_dp');
  const rd=document.getElementById('radio_dp');
  if(t==='penuh'){
    ep.classList.add('sel-full'); ep.classList.remove('sel-dp');
    ed.classList.remove('sel-dp'); ed.className=ed.className.replace('sel-dp','');
    dp.style.background='#22c55e';
    dd.style.background='transparent'; rd.style.borderColor='rgba(255,255,255,.2)';
  } else {
    ed.classList.add('sel-dp');
    ep.classList.remove('sel-full');
    dd.style.background='#f59e0b'; rd.style.borderColor='#f59e0b';
    dp.style.background='transparent';
  }
  updateCalc();
}

// ═══ SELECT METODE ════════════════════════════════════════════════════════
function selectMetode(key){
  document.querySelectorAll('.metode-btn').forEach(b=>b.classList.remove('sel'));
  document.querySelectorAll('[id^="panel_"]').forEach(p=>p.classList.add('hidden'));
  sMetode=key;
  document.getElementById('f_metode').value=key;
  document.getElementById('mb_'+key).classList.add('sel');
  const panel=document.getElementById('panel_'+key);
  panel.classList.remove('hidden'); panel.classList.add('slide-in');
  // Summary
  const rek=REK[key];
  document.getElementById('s_metode_box').classList.remove('hidden');
  document.getElementById('s_metode_logo').textContent=rek.logo;
  document.getElementById('s_metode_name').textContent=key+' · '+(rek.metode==='e-wallet'?'e-Wallet':'Transfer');
  document.getElementById('s_metode_no').textContent=rek.no;
  checkReady();
}

// ═══ COPY NOMOR ══════════════════════════════════════════════════════════
function copyNo(no, btn){
  navigator.clipboard.writeText(no).then(()=>{
    const ori=btn.textContent; btn.textContent='Tersalin ✓';
    setTimeout(()=>btn.textContent=ori, 2000);
  });
}

// ═══ UPLOAD ══════════════════════════════════════════════════════════════
function handleFile(input){
  const f=input.files[0]; if(!f) return;
  const r=new FileReader();
  r.onload=e=>{
    document.getElementById('ph').classList.add('hidden');
    document.getElementById('prev').classList.remove('hidden');
    document.getElementById('prevImg').src=e.target.result;
    document.getElementById('prevName').textContent='✓ '+f.name+' ('+Math.round(f.size/1024)+'KB)';
    document.getElementById('dropZone').classList.add('done');
    document.getElementById('s_bukti_box').classList.remove('hidden');
    document.getElementById('s_bukti_name').textContent=f.name;
  };
  r.readAsDataURL(f);
}
function handleDrop(e){
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('drag');
  const f=e.dataTransfer.files[0];
  if(f&&f.type.startsWith('image/')){
    const dt=new DataTransfer(); dt.items.add(f);
    document.getElementById('buktiInput').files=dt.files;
    handleFile(document.getElementById('buktiInput'));
  }
}
function clearFile(){
  document.getElementById('buktiInput').value='';
  document.getElementById('ph').classList.remove('hidden');
  document.getElementById('prev').classList.add('hidden');
  document.getElementById('dropZone').classList.remove('done');
  document.getElementById('s_bukti_box').classList.add('hidden');
}

// ═══ REFRESH JADWAL ══════════════════════════════════════════════════════
function refreshJadwal(){
  const tgl=document.getElementById('tanggalInput').value;
  const lapId=sLap.id||document.getElementById('f_lap').value;
  document.getElementById('f_tgl').value=tgl;
  document.getElementById('sum_tgl').textContent=tgl
    ? new Date(tgl+'T00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})
    : '—';
  updateChecklist();
  if(tgl&&lapId) window.location.href='booking.php?tanggal='+tgl+'&lapangan_id='+lapId;
}

// Init
updateCalc(); checkReady();
</script>
</body>
</html>