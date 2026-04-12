<?php
session_start();
include 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// CEK KONEKSI
if (!$koneksi) {
    die("Koneksi database gagal!");
}

// AMBIL DATA RIWAYAT
$query = mysqli_query($koneksi, "
    SELECT b.*, l.nama as nama_lapangan
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    WHERE b.user_id = '$user_id'
    ORDER BY b.tanggal DESC
");

$riwayat = [];
while ($row = mysqli_fetch_assoc($query)) {
    $riwayat[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Riwayat Booking</title>
</head>
<body>

<h2>Riwayat Booking</h2>

<?php if (empty($riwayat)): ?>
    <p>Belum ada riwayat booking.</p>
<?php else: ?>
    <table border="1" cellpadding="10">
        <tr>
            <th>Lapangan</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Durasi</th>
            <th>Total</th>
            <th>Status</th>
        </tr>

        <?php foreach ($riwayat as $r): ?>
        <tr>
            <td><?= $r['nama_lapangan']; ?></td>
            <td><?= $r['tanggal']; ?></td>
            <td><?= $r['jam_mulai']; ?> - <?= $r['jam_selesai']; ?></td>
            <td><?= $r['durasi_jam']; ?> jam</td>
            <td>Rp <?= number_format($r['total_harga']); ?></td>
            <td><?= $r['status']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

</body>
</html>