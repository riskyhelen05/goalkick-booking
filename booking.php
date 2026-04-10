<?php
session_start();
include 'koneksi.php';

// 🔒 Proteksi login
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

// Ambil data lapangan dari database
$lapangan = mysqli_query($koneksi, "SELECT * FROM lapangan");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Lapangan | GoalKick</title>

<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-r from-blue-600 via-purple-600 to-red-500 text-white min-h-screen">

<nav class="fixed w-full backdrop-blur-lg bg-white/30 border-b border-white/20 z-50">
<div class="max-w-7xl mx-auto px-6">
<div class="flex justify-between items-center h-16">
<h1 class="text-xl font-bold text-blue-800">GoalKick</h1>
</div>
</div>
</nav>

<div class="max-w-6xl mx-auto px-5 pt-28 pb-10">

<h1 class="text-3xl font-bold text-center mb-10">
⚽ Sistem Booking Lapangan Futsal
</h1>

<div class="bg-white/20 backdrop-blur-xl p-8 rounded-xl shadow-xl border border-white/30 mb-10">

<!-- ✅ FORM BENAR -->
<form method="POST" action="proses_booking.php" class="grid grid-cols-1 md:grid-cols-2 gap-5">

<div class="md:col-span-2">
<label>Nama</label>
<input type="text" name="nama" required class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div>
<label>No WhatsApp</label>
<input type="text" name="wa" required class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div>
<label>Pilih Lapangan</label>
<select name="lapangan_id" required class="w-full p-3 rounded-lg bg-white/20 border">

<option value="">Pilih Lapangan</option>

<?php while($row = mysqli_fetch_assoc($lapangan)) { ?>
<option value="<?= $row['id'] ?>">
<?= $row['nama'] ?> - Rp<?= $row['harga_per_jam'] ?>/jam
</option>
<?php } ?>

</select>
</div>

<div>
<label>Tanggal</label>
<input type="date" name="tanggal" required class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div>
<label>Jam Mulai</label>
<input type="time" name="jam_mulai" required class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div>
<label>Jam Selesai</label>
<input type="time" name="jam_selesai" required class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div class="md:col-span-2">
<button type="submit"
class="bg-gradient-to-r from-blue-600 to-red-500 px-6 py-3 rounded-lg w-full">
Simpan Booking
</button>
</div>

</form>

</div>
</div>

</body>
</html>