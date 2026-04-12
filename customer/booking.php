<?php
session_start();
include '../koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: login.php'); exit;
}
$user_id = intval($_SESSION['user_id']);

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'booking') {
    $lapangan_id = intval($_POST['lapangan_id'] ?? 0);
    $tanggal     = $koneksi->real_escape_string($_POST['tanggal'] ?? '');
    $jam_mulai   = $koneksi->real_escape_string($_POST['jam_mulai'] ?? '');
    $durasi      = intval($_POST['durasi'] ?? 1);

    if ($lapangan_id && $tanggal && $jam_mulai && $durasi > 0) {
        if ($tanggal < date('Y-m-d')) {
            $error = 'Tanggal tidak boleh di masa lalu.';
        } else {
            $jam_selesai_ts = strtotime("$tanggal $jam_mulai") + ($durasi * 3600);
            $jam_selesai    = date('H:i:s', $jam_selesai_ts);
            if ($jam_selesai > '22:00:00') {
                $error = 'Jam selesai melebihi jam operasional (22:00).';
            } else {
                $rLap = $koneksi->query("SELECT harga_per_jam FROM lapangan WHERE id=$lapangan_id AND status='tersedia'");
                if ($rLap->num_rows === 0) {
                    $error = 'Lapangan tidak tersedia.';
                } else {
                    $harga_per_jam = intval($rLap->fetch_assoc()['harga_per_jam']);
                    $total_harga   = $harga_per_jam * $durasi;
                    $kode_booking  = 'BK' . strtoupper(substr(md5(uniqid()), 0, 8));
                    $sql = "INSERT INTO booking (kode_booking, user_id, lapangan_id, tanggal, jam_mulai, jam_selesai, durasi_jam, harga_saat_booking, total_harga, status)
                            VALUES ('$kode_booking', $user_id, $lapangan_id, '$tanggal', '$jam_mulai', '$jam_selesai', $durasi, $harga_per_jam, $total_harga, 'pending')";
                    if ($koneksi->query($sql)) {
                        $booking_id = $koneksi->insert_id;
                        $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe)
                            VALUES ($user_id, $booking_id, 'Booking Berhasil Dibuat', 'Booking kamu dengan kode $kode_booking telah diterima. Silakan lakukan pembayaran segera.', 'info')");
                        $success = "Booking berhasil! Kode: <strong>$kode_booking</strong>. Silakan lakukan pembayaran.";
                    } else {
                        $error = 'Jadwal sudah terisi atau lapangan sedang diblokir.';
                    }
                }
            }
        }
    } else {
        $error = 'Semua field wajib diisi.';
    }
}

$lapangans = [];
$rLap = $koneksi->query("SELECT * FROM lapangan WHERE status = 'tersedia' ORDER BY harga_per_jam ASC");
while ($row = $rLap->fetch_assoc()) $lapangans[] = $row;

$tanggal_cek = $_GET['tanggal'] ?? date('Y-m-d');
$lap_cek     = intval($_GET['lapangan_id'] ?? 0);
$booked_slots = [];
if ($lap_cek && $tanggal_cek) {
    $esc_tgl = $koneksi->real_escape_string($tanggal_cek);
    $rSlots = $koneksi->query("SELECT jam_mulai, jam_selesai FROM booking WHERE lapangan_id=$lap_cek AND tanggal='$esc_tgl' AND status IN ('pending','dikonfirmasi')");
    while ($row = $rSlots->fetch_assoc()) $booked_slots[] = $row;
    $rBlokir = $koneksi->query("SELECT jam_mulai, jam_selesai FROM jadwal_blokir WHERE lapangan_id=$lap_cek AND tanggal='$esc_tgl'");
    while ($row = $rBlokir->fetch_assoc()) $booked_slots[] = $row;
}

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$days_id   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$today_str = $days_id[date('w')] . ', ' . date('j') . ' ' . $months_id[(int)date('n')] . ' ' . date('Y');
$rUser = $koneksi->query("SELECT nama FROM users WHERE id=$user_id");
$user  = $rUser->fetch_assoc();
$rNotif = $koneksi->query("SELECT COUNT(*) as c FROM notifikasi WHERE user_id=$user_id AND is_read=0");
$unread = $rNotif->fetch_assoc()['c'];
$jam_ops = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00'];
$jenis_icon = ['sintetis'=>'⚽','vinyl'=>'🏟️','rumput'=>'🌿'];

function isSlotBooked($jam, $booked_slots) {
    $ts = strtotime($jam);
    foreach ($booked_slots as $s) {
        if ($ts >= strtotime($s['jam_mulai']) && $ts < strtotime($s['jam_selesai'])) return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartFutsal — Booking Lapangan</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['DM Sans','sans-serif'],display:['Bebas Neue','sans-serif']},colors:{brand:{red:'#e8192c',red2:'#ff3344'}}}}}</script>
  <style>
    body{background:#0a0a0a;color:#f0f0f0;}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:#2a2a2a;border-radius:4px}
    .nav-link{transition:all .15s;padding-bottom:2px}
    .nav-link.active{color:#ff3344;border-bottom:2px solid #e8192c}
    .card{background:#161616;border:1px solid rgba(255,255,255,0.06)}
    .slot-available{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:#22c55e;cursor:pointer;transition:all .15s}
    .slot-available:hover,.slot-available.selected{background:rgba(34,197,94,0.25);border-color:#22c55e}
    .slot-booked{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#6b7280;cursor:not-allowed}
    .slot-past{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);color:#374151;cursor:not-allowed}
    .lap-card{background:#161616;border:2px solid rgba(255,255,255,0.06);transition:all .2s;cursor:pointer}
    .lap-card:hover{border-color:rgba(232,25,44,0.4)}
    .lap-card.selected{border-color:#e8192c;background:rgba(232,25,44,0.06)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp .3s ease forwards}
    input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);cursor:pointer}
  </style>
</head>
<body class="font-sans min-h-screen">

<!-- NAVBAR -->
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
        <a href="logout.php" class="ml-2 text-xs text-gray-600 hover:text-red-400 transition no-underline">Keluar</a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<div style="background:linear-gradient(180deg,#1a0808 0%,#0a0a0a 100%)">
  <div class="max-w-6xl mx-auto px-5 py-10 fade-up">
    <div class="text-xs text-brand-red tracking-widest uppercase font-semibold mb-2">⚽ Reservasi Sekarang</div>
    <h1 class="font-display text-5xl tracking-widest mb-2">BOOKING <span class="text-brand-red">LAPANGAN</span></h1>
    <p class="text-gray-500 text-sm">Pilih lapangan, tanggal, dan jam mainmu. Cepat, mudah, langsung jalan.</p>
  </div>
</div>

<div class="max-w-6xl mx-auto px-5 pb-16 mt-2">

  <?php if($error):?>
  <div class="mb-5 px-4 py-3 rounded-xl text-sm font-semibold bg-red-500/10 text-red-400 border border-red-500/20 fade-up">❌ <?=htmlspecialchars($error)?></div>
  <?php endif;?>
  <?php if($success):?>
  <div class="mb-5 px-4 py-3 rounded-xl text-sm font-semibold bg-green-500/10 text-green-400 border border-green-500/20 fade-up">
    ✅ <?=$success?> <a href="riwayat.php" class="ml-2 underline text-green-300">Lihat riwayat →</a>
  </div>
  <?php endif;?>

  <div class="grid gap-6" style="grid-template-columns:1fr 360px">

    <!-- KIRI -->
    <div class="space-y-5">

      <!-- STEP 1: PILIH LAPANGAN -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">1</div>
          <span class="font-semibold text-sm">Pilih Lapangan</span>
        </div>
        <?php if(empty($lapangans)):?>
        <p class="text-gray-600 text-sm text-center py-4">Tidak ada lapangan tersedia.</p>
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

      <!-- STEP 2: PILIH TANGGAL -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">2</div>
          <span class="font-semibold text-sm">Pilih Tanggal</span>
        </div>
        <div class="flex gap-3">
          <input type="date" id="tanggalInput" value="<?=htmlspecialchars($tanggal_cek)?>" min="<?=date('Y-m-d')?>"
                 class="flex-1 rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a"
                 onchange="refreshJadwal()"/>
          <button onclick="refreshJadwal()" class="px-5 py-3 rounded-xl bg-brand-red text-white text-sm font-semibold hover:opacity-90 transition">Cek Jadwal</button>
        </div>
      </div>

      <!-- STEP 3: PILIH JAM -->
      <div class="card rounded-2xl p-5 fade-up">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-brand-red flex items-center justify-center text-xs font-bold text-white">3</div>
            <span class="font-semibold text-sm">Pilih Jam Mulai</span>
          </div>
          <div class="flex gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:rgba(34,197,94,0.2);border:1px solid rgba(34,197,94,.4)"></span>Tersedia</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)"></span>Terisi</span>
          </div>
        </div>
        <?php if(!$lap_cek):?>
        <div class="text-center py-8 text-gray-600 text-sm">← Pilih lapangan dan tanggal terlebih dahulu.</div>
        <?php else:?>
        <div class="grid grid-cols-4 gap-2">
          <?php
          $now_ts = time();
          foreach($jam_ops as $jam):
            $slot_ts   = strtotime("$tanggal_cek $jam");
            $is_past   = ($tanggal_cek===date('Y-m-d') && $slot_ts<=$now_ts);
            $is_booked = isSlotBooked($jam, $booked_slots);
            $cls = $is_past?'slot-past':($is_booked?'slot-booked':'slot-available');
            $canClick = !$is_past && !$is_booked;
          ?>
          <div class="<?=$cls?> rounded-xl px-3 py-3 text-center text-xs font-semibold"
               <?=$canClick?"onclick=\"selectSlot(this,'$jam')\"":''?> data-jam="<?=$jam?>">
            <div class="text-sm font-bold"><?=$jam?></div>
            <?php if($is_booked):?><div class="text-xs opacity-60 mt-0.5">Terisi</div>
            <?php elseif($is_past):?><div class="text-xs opacity-50 mt-0.5">Lewat</div>
            <?php else:?><div class="text-xs opacity-60 mt-0.5">Tersedia</div><?php endif;?>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
      </div>

    </div>

    <!-- KANAN: RINGKASAN -->
    <div>
      <form method="POST" id="bookingForm">
        <input type="hidden" name="action"      value="booking">
        <input type="hidden" name="lapangan_id" id="f_lapangan_id" value="<?=$lap_cek?>">
        <input type="hidden" name="tanggal"     id="f_tanggal"     value="<?=htmlspecialchars($tanggal_cek)?>">
        <input type="hidden" name="jam_mulai"   id="f_jam_mulai"   value="">

        <div class="card rounded-2xl p-5 fade-up sticky top-24">
          <div class="font-display text-xl tracking-wider mb-5">RINGKASAN <span class="text-brand-red">BOOKING</span></div>

          <div class="rounded-xl p-4 mb-3" style="background:#1a1a1a">
            <div class="text-xs text-gray-500 mb-1">Lapangan dipilih</div>
            <div id="sum_lapangan" class="font-bold text-white">— Belum dipilih —</div>
          </div>
          <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="rounded-xl p-3" style="background:#1a1a1a">
              <div class="text-xs text-gray-500 mb-1">Tanggal</div>
              <div id="sum_tanggal" class="font-bold text-xs text-white"><?=date('d M Y',strtotime($tanggal_cek))?></div>
            </div>
            <div class="rounded-xl p-3" style="background:#1a1a1a">
              <div class="text-xs text-gray-500 mb-1">Jam Mulai</div>
              <div id="sum_jam" class="font-bold text-xs text-brand-red">— Pilih jam —</div>
            </div>
          </div>

          <div class="mb-4">
            <label class="text-xs text-gray-500 block mb-1.5">Durasi Main</label>
            <select name="durasi" id="durasiSelect" onchange="updateHarga()"
                    class="w-full rounded-xl px-4 py-3 text-sm text-white border border-white/10 outline-none focus:border-brand-red transition" style="background:#1a1a1a">
              <option value="1">1 Jam</option>
              <option value="2" selected>2 Jam</option>
              <option value="3">3 Jam</option>
              <option value="4">4 Jam</option>
            </select>
          </div>

          <div class="rounded-xl p-4 mb-4 border border-brand-red/20" style="background:rgba(232,25,44,0.05)">
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs text-gray-400">Total Pembayaran</span>
              <div id="sum_total" class="font-display text-2xl text-brand-red tracking-wide">Rp —</div>
            </div>
            <div class="text-xs text-gray-600" id="sum_detail">Pilih lapangan dan jam terlebih dahulu</div>
          </div>

          <div class="rounded-xl p-3 mb-4 border border-white/5" style="background:#1a1a1a">
            <div class="text-xs text-gray-500 mb-2 font-semibold">💳 Metode Pembayaran</div>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach(['BRI','BNI','BCA','GoPay','OVO'] as $m):?>
              <span class="text-xs px-2 py-1 rounded-lg text-gray-400" style="background:#222"><?=$m?></span>
              <?php endforeach;?>
            </div>
            <div class="text-xs text-gray-600 mt-2">Konfirmasi manual oleh admin setelah transfer.</div>
          </div>

          <button type="submit" id="btnBooking"
                  class="w-full py-3.5 rounded-xl bg-brand-red text-white font-bold text-sm hover:opacity-90 transition"
                  disabled style="opacity:.4;cursor:not-allowed">
            ⚡ Buat Booking
          </button>
          <p class="text-xs text-gray-600 text-center mt-2">Booking <em>pending</em> hingga pembayaran dikonfirmasi admin.</p>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
let selLap = {id:<?=$lap_cek?:0?>, nama:'', harga:0};
<?php foreach($lapangans as $l):?>
if(selLap.id===<?=$l['id']?>){selLap.nama='<?=addslashes(htmlspecialchars($l['nama']))?>'; selLap.harga=<?=$l['harga_per_jam']?>;}
<?php endforeach;?>
if(selLap.id>0) document.getElementById('sum_lapangan').textContent = selLap.nama || 'Lapangan #'+selLap.id;

function selectLapangan(id,nama,harga){
  selLap={id,nama,harga};
  document.getElementById('f_lapangan_id').value=id;
  document.getElementById('sum_lapangan').textContent=nama;
  document.querySelectorAll('.lap-card').forEach(c=>c.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  updateHarga(); refreshJadwal();
}
function selectSlot(el,jam){
  document.querySelectorAll('.slot-available').forEach(s=>s.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('f_jam_mulai').value=jam;
  document.getElementById('sum_jam').textContent=jam+' WIB';
  updateHarga();
}
function updateHarga(){
  const durasi=parseInt(document.getElementById('durasiSelect').value);
  const jam=document.getElementById('f_jam_mulai').value;
  const btn=document.getElementById('btnBooking');
  if(selLap.harga&&jam){
    const total=selLap.harga*durasi;
    document.getElementById('sum_total').textContent='Rp '+total.toLocaleString('id-ID');
    document.getElementById('sum_detail').textContent=selLap.nama+' · '+durasi+' jam · mulai '+jam;
    btn.disabled=false; btn.style.opacity='1'; btn.style.cursor='pointer';
  } else {
    document.getElementById('sum_total').textContent='Rp —';
    document.getElementById('sum_detail').textContent='Pilih lapangan dan jam terlebih dahulu';
    btn.disabled=true; btn.style.opacity='.4'; btn.style.cursor='not-allowed';
  }
}
function refreshJadwal(){
  const tgl=document.getElementById('tanggalInput').value;
  const lapId=selLap.id||document.getElementById('f_lapangan_id').value;
  if(tgl&&lapId) window.location.href='booking.php?tanggal='+tgl+'&lapangan_id='+lapId;
}
</script>
</body>
</html>