<?php
include 'koneksi.php';
session_start();
include 'koneksi.php';

$error = "";

// Ambil cookie
$remember_email = "";
if(isset($_COOKIE['remember_user'])){
    $remember_email = $_COOKIE['remember_user'];
}

if(isset($_POST['submit'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = mysqli_query($koneksi, $query);

    if(mysqli_num_rows($result) > 0){
        $_SESSION['email'] = $email;

        // REMEMBER ME
        if(isset($_POST['remember'])){
            setcookie("remember_user", $email, time() + (86400 * 7)); // 7 hari
        } else {
            $error = "Password salah!";
        }

        header("Location: customer/booking.php");
        exit;
    } else {
        echo "<script>alert('email atau Password salah!');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Smart Futsal</title>

<script src="https://cdn.tailwindcss.com"></script>

<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
@keyframes fadeUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

</head>

<body class="min-h-screen flex flex-col font-[Plus Jakarta Sans] bg-[linear-gradient(to_right,rgba(0,0,0,0.55),rgba(0,0,0,0.2),rgba(139,0,0,0.45)),url('https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=1600&q=80&fit=crop')] bg-cover bg-center">

<!-- Navbar -->
<nav class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-8 py-4 bg-black/35 backdrop-blur-md border-b border-white/10">
    
    <a href="#" class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-red-600 flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                <path stroke-linecap="round" stroke-width="1.5" d="M12 6v12M6 12h12M8.5 8.5l7 7M15.5 8.5l-7 7"/>
            </svg>
        </div>
        <span class="text-white font-semibold text-[15px] tracking-wide">
            Smart<span class="text-red-400">Futsal</span>
        </span>
    </a>

    <div class="hidden md:flex items-center gap-8 text-sm text-white/70">
        <a href="#" class="hover:text-white transition-colors">Beranda</a>
        <a href="#" class="hover:text-white transition-colors">Lapangan</a>
        <a href="#" class="hover:text-white transition-colors">Harga</a>
        <a href="#" class="hover:text-white transition-colors">Kontak</a>
    </div>

    <a href="daftar.php" class="text-sm text-white/70 border border-white/20 rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white transition-all">
        Daftar
    </a>

</nav>

<!-- Content -->
<div class="flex-1 flex items-center justify-center min-h-screen px-6 pt-20 pb-10">

<div class="w-full max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

<!-- HERO -->
<div class="hidden lg:flex flex-col gap-6 opacity-0 translate-y-5 animate-[fadeUp_0.6s_ease_forwards]">

    <span class="self-start text-xs font-medium px-3 py-1.5 rounded-full uppercase tracking-widest
    bg-red-600/30 border border-red-500/50 text-red-300 animate-pulse">
        ⚽ Booking Digital
    </span>

    <h1 class="font-[Bebas Neue] text-white leading-none">
        <span class="text-7xl block">Booking</span>
        <span class="text-7xl block text-red-400">Lapangan</span>
        <span class="text-7xl block">Mudah.</span>
    </h1>

    <p class="text-white/55 text-[15px] max-w-sm">
        Pesan lapangan futsal favoritmu secara real-time, bayar digital, dan dapatkan konfirmasi otomatis.
    </p>

</div>

<!-- FORM -->
<div class="w-full max-w-md mx-auto lg:ml-auto">

<div class="rounded-2xl p-8 backdrop-blur-[18px] bg-white/10 border border-white/20 opacity-0 translate-y-5 animate-[fadeUp_0.6s_ease_forwards]">

<div class="mb-7">
    <h2 class="text-white text-3xl font-semibold mb-1">Masuk</h2>
    <p class="text-white/50 text-sm">
        Belum punya akun?
        <a href="daftar.php" class="text-red-400 hover:text-red-300 ml-1">Daftar sekarang</a>
    </p>
</div>

<form method="POST" class="flex flex-col gap-4">

<!-- Email -->
<div>
<label class="text-white/60 text-xs mb-1 uppercase">Email</label>
<input type="email" name="email"
class="w-full rounded-xl px-4 py-3 text-sm bg-white/10 border border-white/20 text-white placeholder:text-white/40 focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/25 transition">
</div>

<!-- Password -->
<div>
<label class="text-white/60 text-xs mb-1 uppercase">Password</label>
<div class="relative">
<input type="password" id="password" name="password"
class="w-full rounded-xl px-4 py-3 text-sm bg-white/10 border border-white/20 text-white focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/25 transition">

<button type="button" onclick="togglePass('password', this)"
class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-red-500">
👁
</button>

</div>
</div>

<!-- Remember -->
<div class="flex justify-between text-xs text-white/60">
<label class="flex gap-2 items-center">
<input type="checkbox" name="remember" class="accent-red-600">
Ingat saya
</label>
<a href="lupa-password.php" class="text-red-400">Lupa password?</a>
</div>

<!-- Button -->
<button class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl transition" name="submit">
Masuk ke Akun
</button>

</form>

</div>
</div>

</div>
</div>

<script>
function togglePass(id, btn){
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>