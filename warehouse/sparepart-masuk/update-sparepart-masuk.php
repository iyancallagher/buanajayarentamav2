<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../sparepart-masuk.php");
    exit;
}

$masuk_id = (int)($_POST['masuk_id'] ?? 0);
$qty_baru  = (int)($_POST['quantity'] ?? 0);

if ($masuk_id <= 0 || $qty_baru <= 0) {
    $_SESSION['form_error'] = "Kuantitas data input baru tidak valid.";
    header("Location: ../sparepart-masuk.php");
    exit;
}

// Update kuantitas riwayat masuk secara langsung tanpa memengaruhi stok master gudang
$query = "UPDATE sparepart_masuk_wr SET quantity = ? WHERE id = ?";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $qty_baru, $masuk_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = "Kuantitas riwayat sparepart masuk berhasil diperbarui.";
} else {
    $_SESSION['form_error'] = "Gagal memperbarui kuantitas riwayat di database.";
}

header("Location: ../sparepart-masuk.php");
exit;