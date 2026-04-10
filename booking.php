<?php
session_start();
include 'koneksi.php';

// 🔒 Proteksi login
if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

// Ambil data lapangan
$lapangan = mysqli_query($koneksi, "SELECT * FROM lapangan");

// Ambil filter
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$lapangan_id = isset($_GET['lapangan_id']) ? $_GET['lapangan_id'] : 1;

// Ambil jam yang sudah dibooking
$booking = mysqli_query($koneksi, "
SELECT jam_mulai, jam_selesai 
FROM booking 
WHERE tanggal='$tanggal' AND lapangan_id='$lapangan_id'
");

$jam_terpakai = [];

while($b = mysqli_fetch_assoc($booking)){
    for($i = $b['jam_mulai']; $i < $b['jam_selesai']; $i++){
        $jam_terpakai[] = $i;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Lapangan</title>

<script src="https://cdn.tailwindcss.com"></script>

<style>
body{
    background: linear-gradient(
        to right,
        rgba(0,0,0,0.7),
        rgba(0,0,0,0.4)
    ),
    url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=1600');
    background-size: cover;
    background-position: center;
}
</style>

</head>

<body class="text-white min-h-screen">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full bg-black/40 backdrop-blur-md border-b border-white/10">
<div class="max-w-7xl mx-auto px-6 py-4">
<h1 class="font-semibold">GoalKick ⚽</h1>
</div>
</nav>

<div class="pt-24 max-w-6xl mx-auto px-5">

<!-- FORM -->
<div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl p-8">

<form method="POST" action="proses_booking.php" class="grid grid-cols-1 md:grid-cols-2 gap-5">

<!-- Nama -->
<div class="md:col-span-2">
<label>Nama</label>
<input type="text" name="nama" required class="w-full p-3 rounded bg-white/20 border">
</div>

<!-- WA -->
<div>
<label>No WA</label>
<input type="text" name="wa" required class="w-full p-3 rounded bg-white/20 border">
</div>

<!-- Lapangan -->
<div>
<label>Lapangan</label>
<select name="lapangan_id" id="lapangan" class="w-full p-3 rounded bg-white/20 border">

<option value="">Pilih</option>

<?php while($row = mysqli_fetch_assoc($lapangan)) { ?>
<option value="<?= $row['id'] ?>" data-harga="<?= $row['harga_per_jam'] ?>">
<?= $row['nama'] ?> - Rp<?= $row['harga_per_jam'] ?>
</option>
<?php } ?>

</select>
</div>

<!-- Tanggal -->
<div>
<label>Tanggal</label>
<input type="date" name="tanggal" value="<?= $tanggal ?>" class="w-full p-3 rounded bg-white/20 border">
</div>

<!-- SLOT JAM -->
<div class="md:col-span-2">
<label>Pilih Jam</label>

<div class="grid grid-cols-4 md:grid-cols-6 gap-3 mt-2">

<?php for($i=8;$i<=22;$i++):
$isBooked = in_array($i,$jam_terpakai);
?>

<label>
<input type="radio" name="jam_mulai" value="<?= $i ?>" class="hidden peer" <?= $isBooked?'disabled':'' ?>>

<div class="p-3 text-center rounded border
<?= $isBooked ? 'bg-gray-500' : 'bg-white/20 hover:bg-green-500 peer-checked:bg-green-500 cursor-pointer' ?>">
<?= $i ?>:00
</div>
</label>

<?php endfor; ?>

</div>
</div>

<input type="hidden" name="jam_selesai" id="jam_selesai">

<!-- PEMBAYARAN -->
<div class="md:col-span-2">
<label>Metode Pembayaran</label>

<div class="flex gap-3 mt-2">
<label class="flex-1">
<input type="radio" name="metode" value="gopay" class="hidden peer" checked>
<div class="p-3 text-center border rounded peer-checked:bg-red-500">GoPay</div>
</label>

<label class="flex-1">
<input type="radio" name="metode" value="ovo" class="hidden peer">
<div class="p-3 text-center border rounded peer-checked:bg-purple-500">OVO</div>
</label>
</div>
</div>

<!-- TOTAL -->
<div class="md:col-span-2 bg-white/10 p-4 rounded border">
<p>Harga per jam:</p>
<p id="harga">Rp 0</p>

<p class="mt-2">Total:</p>
<p id="total" class="text-xl text-green-400">Rp 0</p>
</div>

<!-- BUTTON -->
<div class="md:col-span-2">
<button class="w-full bg-red-500 py-3 rounded">Booking</button>
</div>

</form>

</div>

</div>

<script>
let hargaPerJam = 0;
let jamMulai = 0;
let jamSelesai = 0;

// ambil harga
document.getElementById('lapangan').addEventListener('change', function(){
    let selected = this.options[this.selectedIndex];
    hargaPerJam = selected.getAttribute('data-harga') || 0;

    document.getElementById('harga').innerText = "Rp " + formatRupiah(hargaPerJam);
    hitungTotal();
});

// pilih jam
document.querySelectorAll('input[name="jam_mulai"]').forEach(radio=>{
    radio.addEventListener('change', function(){
        jamMulai = parseInt(this.value);
        jamSelesai = jamMulai + 1;

        document.getElementById('jam_selesai').value = jamSelesai;
        hitungTotal();
    });
});

function hitungTotal(){
    if(hargaPerJam && jamMulai){
        let total = hargaPerJam * (jamSelesai - jamMulai);
        document.getElementById('total').innerText = "Rp " + formatRupiah(total);
    }
}

function formatRupiah(angka){
    return new Intl.NumberFormat('id-ID').format(angka);
}
</script>

</body>
</html>