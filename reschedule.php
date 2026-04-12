<?php
session_start();
include 'koneksi.php';

$id = $_GET['id'];

$data = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT b.*, l.nama as nama_lapangan 
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    WHERE b.id = '$id'
"));

if (isset($_POST['update'])) {

    $tanggal = $_POST['tanggal'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    $durasi = (strtotime($jam_selesai) - strtotime($jam_mulai)) / 3600;

    if ($durasi <= 0) {
        echo "<script>alert('Jam tidak valid!');</script>";
    } else {
        $total = $data['harga_saat_booking'] * $durasi;

        $update = mysqli_query($koneksi, "
            UPDATE booking SET
            tanggal='$tanggal',
            jam_mulai='$jam_mulai',
            jam_selesai='$jam_selesai',
            durasi_jam='$durasi',
            total_harga='$total'
            WHERE id='$id'
        ");

        if ($update) {
            echo "<script>alert('Reschedule berhasil!');location='riwayat.php';</script>";
        } else {
            echo "<script>alert('Gagal: ".mysqli_error($koneksi)."');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reschedule Booking</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #000;
            color: #fff;
        }

        .container {
            width: 400px;
            margin: 80px auto;
            background: #111;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #333;
        }

        h2 {
            text-align: center;
            color: red;
            margin-bottom: 20px;
        }

        .info-box {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #ccc;
        }

        label {
            font-size: 13px;
            display: block;
            margin-top: 10px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 8px;
            border: none;
            background: #222;
            color: white;
        }

        input:focus {
            outline: 1px solid red;
        }

        .btn {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: red;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #cc0000;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #ccc;
            text-decoration: none;
            font-size: 13px;
        }

        .back:hover {
            color: white;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>🔄 Reschedule</h2>

    <div class="info-box">
        <b><?= $data['nama_lapangan']; ?></b><br>
        📅 <?= $data['tanggal']; ?><br>
        ⏰ <?= $data['jam_mulai']; ?> - <?= $data['jam_selesai']; ?><br>
        💰 Rp <?= number_format($data['total_harga']); ?>
    </div>

    <form method="POST">

        <label>Tanggal Baru</label>
        <input type="date" name="tanggal" value="<?= $data['tanggal']; ?>" required>

        <label>Jam Mulai</label>
        <input type="time" name="jam_mulai" value="<?= $data['jam_mulai']; ?>" required>

        <label>Jam Selesai</label>
        <input type="time" name="jam_selesai" value="<?= $data['jam_selesai']; ?>" required>

        <button type="submit" name="update" class="btn">Simpan Perubahan</button>

    </form>

    <a href="riwayat.php" class="back">← Kembali ke Riwayat</a>

</div>

</body>
</html>