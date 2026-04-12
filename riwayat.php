<?php
session_start();
include 'koneksi.php';

// sementara untuk testing
$_SESSION['user_id'] = 1;

$user_id = $_SESSION['user_id'];

$query = mysqli_query($koneksi, "
    SELECT b.*, l.nama as nama_lapangan
    FROM booking b
    LEFT JOIN lapangan l ON b.lapangan_id = l.id
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
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #000;
            color: #fff;
        }

        .container {
            width: 90%;
            margin: auto;
            padding: 30px 0;
        }

        h2 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #ff0000;
        }

        .card {
            background: #111;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lapangan {
            font-size: 20px;
            font-weight: bold;
        }

        .info {
            font-size: 14px;
            color: #ccc;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 5px;
            display: inline-block;
        }

        .pending { background: orange; }
        .dikonfirmasi { background: green; }
        .selesai { background: gray; }
        .dibatalkan { background: red; }

        .right {
            text-align: right;
        }

        .harga {
            font-size: 18px;
            font-weight: bold;
        }

        .btn {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }

        .reschedule {
            background: #fff;
            color: #000;
        }

        .batal {
            background: red;
            color: #fff;
        }

        .disabled {
            background: #555;
            cursor: not-allowed;
        }

    </style>
</head>
<body>

<div class="container">
    <h2>⚽ Riwayat Booking</h2>

    <?php foreach ($riwayat as $r): ?>

        <?php
        $hari_ini = date('Y-m-d');
        $boleh_batal = (strtotime($r['tanggal']) - strtotime($hari_ini)) >= 86400;
        ?>

        <div class="card">

            <div>
                <div class="lapangan"><?= $r['nama_lapangan']; ?></div>
                <div class="info">
                    📅 <?= $r['tanggal']; ?> |
                    ⏰ <?= $r['jam_mulai']; ?> - <?= $r['jam_selesai']; ?> |
                    ⌛ <?= $r['durasi_jam']; ?> jam
                </div>

                <div class="status <?= $r['status']; ?>">
                    <?= ucfirst($r['status']); ?>
                </div>
            </div>

            <div class="right">
                <div class="harga">Rp <?= number_format($r['total_harga']); ?></div>

                <!-- RESCHEDULE -->
                <?php if ($r['status'] != 'selesai' && $r['status'] != 'dibatalkan'): ?>
                    <a href="reschedule.php?id=<?= $r['id']; ?>" class="btn reschedule">Reschedule</a>
                <?php endif; ?>

                <!-- BATAL -->
                <?php if ($r['status'] != 'selesai' && $r['status'] != 'dibatalkan'): ?>
                    
                    <?php if ($boleh_batal): ?>
                        <a href="batal.php?id=<?= $r['id']; ?>" class="btn batal"
                           onclick="return confirm('Yakin mau batalkan booking?')">
                           Batalkan
                        </a>
                    <?php else: ?>
                        <span class="btn disabled">Tidak bisa batal</span>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

        </div>

    <?php endforeach; ?>

</div>

</body>
</html>