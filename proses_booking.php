<?php
session_start();
include 'koneksi.php'; // sesuaikan path

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// AMBIL DATA FORM (SESUAIKAN DENGAN HTML)
$nama      = $_POST['nama'] ?? '';
$no_hp     = $_POST['wa'] ?? '';
$lapangan  = $_POST['lapangan'] ?? '';
$tanggal   = $_POST['tanggal'] ?? '';
$jam_mulai = $_POST['jam'] ?? '';
$durasi    = intval($_POST['durasi_val'] ?? 0);
$metode    = $_POST['metode'] ?? '';
$tipe      = $_POST['tipe'] ?? '';

// VALIDASI
if (!$nama || !$no_hp || !$lapangan || !$tanggal || !$jam_mulai || !$durasi) {
    die("Data tidak lengkap!");
}

// ── KONVERSI LAPANGAN ──
$map_lap = [
    'Lapangan A' => 'A',
    'Lapangan B' => 'B',
    'Lapangan C' => 'C',
    'Lapangan D' => 'D'
];

$lap_kode = $map_lap[$lapangan] ?? 'A';

// ── HARGA ──
$harga_list = [
    'A' => 180000,
    'B' => 160000,
    'C' => 130000,
    'D' => 100000
];

$harga = $harga_list[$lap_kode];

// ── HITUNG JAM ──
$start = strtotime("$tanggal $jam_mulai:00");
$end   = $start + ($durasi * 3600);

$jam_mulai_db   = date('H:i:s', $start);
$jam_selesai_db = date('H:i:s', $end);

if ($jam_selesai_db > '22:00:00') {
    die("Jam melebihi operasional!");
}

// ── CEK BENTROK (ANTI DOUBLE BOOKING) ──
$cek = $koneksi->query("
    SELECT id FROM booking
    WHERE lapangan_id = '$lap_kode'
    AND tanggal = '$tanggal'
    AND (
        jam_mulai < '$jam_selesai_db'
        AND jam_selesai > '$jam_mulai_db'
    )
");

if ($cek->num_rows > 0) {
    die("Jadwal sudah dibooking!");
}

// ── TOTAL ──
$total = $harga * $durasi;
$bayar = ($tipe == 'DP') ? $total * 0.5 : $total;

// ── UPLOAD BUKTI ──
$bukti = '';
if (!empty($_FILES['bukti']['name'])) {

    $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];

    if (!in_array($ext, $allowed)) {
        die("Format harus JPG/PNG/WEBP!");
    }

    if ($_FILES['bukti']['size'] > 5 * 1024 * 1024) {
        die("Max 5MB!");
    }

    $bukti = 'bukti_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['bukti']['tmp_name'], "upload/" . $bukti);
}

// ── KODE BOOKING ──
$kode = "BK" . date('YmdHis');

// ── INSERT BOOKING ──
$koneksi->query("
    INSERT INTO booking (
        user_id, kode_booking, lapangan_id,
        nama_penyewa, no_hp,
        tanggal, jam_mulai, jam_selesai,
        durasi_jam, total_harga, status
    ) VALUES (
        '$user_id', '$kode', '$lap_kode',
        '$nama', '$no_hp',
        '$tanggal', '$jam_mulai_db', '$jam_selesai_db',
        '$durasi', '$total', 'pending'
    )
");

$booking_id = $koneksi->insert_id;

// ── INSERT PEMBAYARAN ──
$koneksi->query("
    INSERT INTO pembayaran (
        booking_id, metode, tipe, jumlah, bukti, status
    ) VALUES (
        '$booking_id', '$metode', '$tipe', '$bayar', '$bukti', 'pending'
    )
");

// ── REDIRECT ──
header("Location: riwayat.php?success=1");
exit;
?>