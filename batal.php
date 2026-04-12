<?php
session_start();
include 'koneksi.php';

$id = $_GET['id'];

$data = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT tanggal FROM booking WHERE id='$id'
"));

$hari_ini = date('Y-m-d');

// cek H-1
if ((strtotime($data['tanggal']) - strtotime($hari_ini)) < 86400) {
    echo "<script>alert('Tidak bisa membatalkan di hari H!');location='riwayat.php';</script>";
    exit;
}

// update status
mysqli_query($koneksi, "
    UPDATE booking SET status='dibatalkan' WHERE id='$id'
");

echo "<script>alert('Booking berhasil dibatalkan');location='riwayat.php';</script>";