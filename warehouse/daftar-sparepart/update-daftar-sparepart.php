<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../daftar-sparepart.php");
    exit;
}

$pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
$qty_baru     = (int)($_POST['quantity'] ?? 0);

if ($pengajuan_id <= 0 || $qty_baru <= 0) {
    $_SESSION['form_error'] = "Input data kuantitas baru tidak valid.";
    header("Location: edit-daftar-sparepart.php");
    exit;
}

// Jalankan update data kuantitas item pengajuan
$query = "UPDATE pengajuan_sparepart SET quantity = ? WHERE id = ? AND status = 'setuju'";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $qty_baru, $pengajuan_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = "Kuantitas unit pengajuan sparepart berhasil disesuaikan.";
} else {
    $_SESSION['form_error'] = "Gagal memperbarui kuantitas pengajuan di database.";
}

header("Location: ../daftar-sparepart.php");
exit;