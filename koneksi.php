<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "futsal_booking";
$port = 3307; // ganti ke 3306 kalau error

$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Optional: set charset biar aman
mysqli_set_charset($koneksi, "utf8mb4");
?>