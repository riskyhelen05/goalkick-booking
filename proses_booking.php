<?php
session_start();
include 'koneksi.php';

$user_id = $_SESSION['user_id'];

$nama = $_POST['nama'];
$wa = $_POST['wa'];
$lapangan_id = $_POST['lapangan_id'];
$tanggal = $_POST['tanggal'];
$jam_mulai = $_POST['jam_mulai'];
$jam_selesai = $_POST['jam_selesai'];

// Ambil harga
$get = mysqli_query($koneksi, "SELECT harga_per_jam FROM lapangan WHERE id=$lapangan_id");
$data = mysqli_fetch_assoc($get);

$durasi = (strtotime($jam_selesai) - strtotime($jam_mulai)) / 3600;
$total = $durasi * $data['harga_per_jam'];

$query = "INSERT INTO booking 
(user_id, lapangan_id, tanggal, jam_mulai, jam_selesai, durasi_jam, harga_saat_booking, total_harga)
VALUES 
('$user_id','$lapangan_id','$tanggal','$jam_mulai','$jam_selesai','$durasi','".$data['harga_per_jam']."','$total')";

if(mysqli_query($koneksi, $query)){
    echo "<script>alert('Booking berhasil');window.location='booking.php';</script>";
}else{
    echo "<script>alert('Gagal booking');</script>";
}
?>