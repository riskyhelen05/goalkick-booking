<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    die("login dulu");
}

$user_id = $_SESSION['user_id'];

// VALIDASI
if(
!isset($_POST['lapangan_id']) ||
!isset($_POST['tanggal']) ||
!isset($_POST['jam_mulai']) ||
!isset($_POST['jam_selesai']) ||
!isset($_POST['durasi']) ||
!isset($_POST['harga']) ||
!isset($_POST['total'])
){
    header("Location: booking.php");
    exit;
}

$lapangan_id = $_POST['lapangan_id'];
$tanggal     = $_POST['tanggal'];
$jam_mulai   = $_POST['jam_mulai'];
$jam_selesai = $_POST['jam_selesai'];
$durasi      = $_POST['durasi'];
$harga       = $_POST['harga'];
$total       = $_POST['total'];

$kode_booking = "BK" . time();

$cek = mysqli_query($koneksi,"
SELECT * FROM booking
WHERE lapangan_id = '$lapangan_id'
AND tanggal = '$tanggal'
AND (
    (jam_mulai < '$jam_selesai' AND jam_selesai > '$jam_mulai')
)
");

if(mysqli_num_rows($cek) > 0){
    echo "Jadwal sudah dibooking";
    exit;
}

// ✅ INSERT BOOKING
$query = "INSERT INTO booking 
(kode_booking, user_id, lapangan_id, tanggal, jam_mulai, jam_selesai, durasi_jam, harga_saat_booking, total_harga, status)
VALUES 
('$kode_booking', '$user_id', '$lapangan_id', '$tanggal', '$jam_mulai', '$jam_selesai', '$durasi', '$harga', '$total', 'pending')";

if (mysqli_query($koneksi, $query)) {

    $booking_id = mysqli_insert_id($koneksi);

    // Ambil nama lapangan
    $lap = mysqli_query($koneksi, "SELECT nama FROM lapangan WHERE id='$lapangan_id'");
    $lapangan = mysqli_fetch_assoc($lap);
    $nama_lapangan = $lapangan['nama'];

    // Format tanggal & jam
    $tgl = date('d M Y', strtotime($tanggal));
    $jam = substr($jam_mulai,0,5);

    // INSERT NOTIFIKASI
    mysqli_query($koneksi, "
        INSERT INTO notifikasi 
        (user_id, booking_id, judul, pesan, tipe)
        VALUES 
        ('$user_id', '$booking_id', 
        '🎉 Booking Berhasil Dibuat!', 
        'Booking $kode_booking di $nama_lapangan pada $tgl jam $jam berhasil dibuat. Menunggu konfirmasi admin.',
        'success')
    ");

    echo "✅ BOOKING + NOTIFIKASI BERHASIL";
} else {
    echo "❌ ERROR: " . mysqli_error($koneksi);
}