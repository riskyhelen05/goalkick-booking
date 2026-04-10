<?php 
include '../koneksi.php';

 $id = $_GET['id'];
$aksi = $_GET['aksi'];

if ($aksi == 'konfirmasi') {
  $status = 'dikonfirmasi';
} elseif ($aksi == 'batal') {
  $status = 'dibatalkan';
} else {
  die("Aksi tidak valid");
}

    $stmt = $koneksi->prepare("UPDATE booking SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

    header("Location: ../admin/admin_booking.php");
    echo $id;
    exit;


?>

