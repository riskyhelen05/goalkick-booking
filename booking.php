<?php
session_start();

// 🔒 Proteksi halaman (harus login dulu)
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}
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

<!-- NAVBAR -->
<nav class="fixed w-full backdrop-blur-lg bg-white/30 border-b border-white/20 z-50">
<div class="max-w-7xl mx-auto px-6">
<div class="flex justify-between items-center h-16">
<h1 class="text-xl font-bold text-blue-800">GoalKick</h1>

<div class="space-x-6 hidden md:flex font-medium">
<a href="#">Home</a>
<a href="#" class="text-blue-700 font-semibold">Booking</a>
</div>

</div>
</div>
</nav>

<!-- CONTAINER -->
<div class="max-w-6xl mx-auto px-5 pt-28 pb-10">

<h1 class="text-3xl font-bold text-center mb-10">
⚽ Sistem Booking Lapangan Futsal
</h1>

<!-- FORM BOOKING -->
<div class="bg-white/20 backdrop-blur-xl p-8 rounded-xl shadow-xl border border-white/30 mb-10">

<form class="grid grid-cols-1 md:grid-cols-2 gap-5">

<!-- Nama -->
<div class="md:col-span-2">
<label>Nama</label>
<input type="text" class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<!-- WA -->
<div>
<label>No WhatsApp</label>
<input type="text" class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<!-- Lapangan -->
<div>
<label>Pilih Lapangan</label>
<select class="w-full p-3 rounded-lg bg-white/20 border">
<option>Pilih Lapangan</option>
<option>Lapangan A</option>
<option>Lapangan B</option>
<option>Lapangan C</option>
</select>
</div>

<!-- Tanggal -->
<div>
<label>Tanggal</label>
<input type="date" class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<!-- Jam -->
<div>
<label>Jam Mulai</label>
<input type="time" class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<div>
<label>Jam Selesai</label>
<input type="time" class="w-full p-3 rounded-lg bg-white/20 border">
</div>

<!-- BUTTON -->
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