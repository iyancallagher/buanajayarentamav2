<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logId   = (int)$_POST['log_id'];
    $qtyBaru = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    // Validasi data input basic
    if ($logId <= 0 || $qtyBaru === false || $qtyBaru <= 0) {
        $_SESSION['form_error'] = "Input kuantitas tidak valid.";
        header("Location: ../sparepart-masuk.php");
        exit;
    }

    // Query lurus: Hanya mengubah catatan quantity di tabel riwayat masuk
    $sql  = "UPDATE sparepart_masuk_wk SET quantity = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $qtyBaru, $logId);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['form_success'] = "Catatan kuantitas riwayat masuk berhasil diubah menjadi " . $qtyBaru . " Pcs.";
    } else {
        $_SESSION['form_error'] = "Gagal memperbarui catatan riwayat di database.";
    }
}

// Kembalikan ke halaman utama monitoring masuk
header("Location: ../workshop-masuk.php");
exit;