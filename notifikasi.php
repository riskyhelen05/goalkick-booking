<?php
session_start();
include 'koneksi.php';

// ── CEK LOGIN ───────────────────────────────────────────────
//if (!isset($_SESSION['user_id'])) {
  //  header('Location: login.php');
    //exit;
//}

//$user_id = intval($_SESSION['user_id']);

// ── TANDAI SEMUA DIBACA ─────────────────────────────────────
if (isset($_GET['mark_all'])) {
    $koneksi->query("UPDATE notifikasi SET is_read=1 WHERE user_id=$user_id");
    header('Location: notifikasi.php');
    exit;
}

// ── TANDAI SATU DIBACA ──────────────────────────────────────
if (isset($_GET['read'])) {
    $nid = intval($_GET['read']);
    $koneksi->query("UPDATE notifikasi SET is_read=1 WHERE id=$nid AND user_id=$user_id");
    header('Location: notifikasi.php');
    exit;
}

// ── AUTO NOTIFIKASI PENGINGAT ───────────────────────────────
$dua_jam_lagi = date('Y-m-d H:i:s', strtotime('+2 hours'));
$sekarang     = date('Y-m-d H:i:s');

$rReminder = $koneksi->query("
    SELECT b.id, b.kode_booking, b.tanggal, b.jam_mulai, l.nama as nama_lapangan
    FROM booking b
    JOIN lapangan l ON b.lapangan_id = l.id
    WHERE b.user_id = $user_id
    AND b.status = 'dikonfirmasi'
    AND CONCAT(b.tanggal, ' ', b.jam_mulai) BETWEEN '$sekarang' AND '$dua_jam_lagi'
    AND b.id NOT IN (
        SELECT booking_id FROM notifikasi
        WHERE user_id=$user_id AND judul LIKE '%Segera Dimulai%'
    )
");

while ($r = $rReminder->fetch_assoc()) {
    $waktu = date('H:i', strtotime($r['jam_mulai']));
    $tgl   = date('d M', strtotime($r['tanggal']));

    $koneksi->query("INSERT INTO notifikasi (user_id, booking_id, judul, pesan, tipe)
        VALUES ($user_id, {$r['id']}, 
        '⏰ Pertandingan Segera Dimulai!',
        'Booking {$r['kode_booking']} di {$r['nama_lapangan']} akan dimulai pukul $waktu WIB pada $tgl.',
        'warning')");
}

// ── FILTER ──────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'semua';
$valid  = ['semua','info','warning','success','unread'];

if (!in_array($filter, $valid)) $filter = 'semua';

$where = "WHERE n.user_id = $user_id";

if ($filter === 'unread') {
    $where .= " AND n.is_read = 0";
} elseif ($filter !== 'semua') {
    $where .= " AND n.tipe = '$filter'";
}

// ── AMBIL DATA ──────────────────────────────────────────────
$notifs = [];

$result = $koneksi->query("
    SELECT n.*, b.kode_booking, b.tanggal, b.jam_mulai, b.jam_selesai,
           l.nama as nama_lapangan
    FROM notifikasi n
    JOIN booking b ON n.booking_id = b.id
    JOIN lapangan l ON b.lapangan_id = l.id
    $where
    ORDER BY n.sent_at DESC
    LIMIT 50
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifs[] = $row;
    }
}

// ── COUNT ───────────────────────────────────────────────────
$counts = ['semua'=>0,'unread'=>0,'info'=>0,'warning'=>0,'success'=>0];

$rC = $koneksi->query("
    SELECT tipe, is_read, COUNT(*) as c
    FROM notifikasi
    WHERE user_id=$user_id
    GROUP BY tipe, is_read
");

if ($rC) {
    while ($r = $rC->fetch_assoc()) {
        $counts['semua'] += $r['c'];

        if (!$r['is_read']) {
            $counts['unread'] += $r['c'];
        }

        if (isset($counts[$r['tipe']])) {
            $counts[$r['tipe']] += $r['c'];
        }
    }
}

$unread = $counts['unread'];

// ── DATA USER ───────────────────────────────────────────────
$rUser = $koneksi->query("SELECT nama FROM users WHERE id=$user_id");
$user  = $rUser ? $rUser->fetch_assoc() : ['nama'=>'User'];

// ── FUNCTION ────────────────────────────────────────────────
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);

    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff/60).' menit lalu';
    if ($diff < 86400) return floor($diff/3600).' jam lalu';
    if ($diff < 604800) return floor($diff/86400).' hari lalu';

    return date('d M Y', strtotime($datetime));
}
?>