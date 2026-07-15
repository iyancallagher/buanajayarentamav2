<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../sparepart-keluar.php");
    exit;
}

$keluar_id = (int)($_POST['keluar_id'] ?? 0);
$qty_baru  = (int)($_POST['quantity'] ?? 0);

if ($keluar_id <= 0 || $qty_baru <= 0) {
    $_SESSION['form_error'] = "Kuantitas data input baru tidak valid.";
    header("Location: ../sparepart-keluar.php");
    exit;
}

// Update kuantitas riwayat keluar secara langsung tanpa menyentuh stok master gudanng
$query = "UPDATE sparepart_keluar_wr SET quantity = ? WHERE id = ?";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $qty_baru, $keluar_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = "Kuantitas riwayat sparepart keluar berhasil diperbarui.";
} else {
    $_SESSION['form_error'] = "Gagal memperbarui kuantitas di database.";
}

header("Location: ../sparepart-keluar.php");
exit;