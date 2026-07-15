<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stokId   = (int)$_POST['stok_id'];
    $stokBaru = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);

    if ($stokId <= 0 || $stokBaru === false || $stokBaru < 0) {
        $_SESSION['form_error'] = "Data input atau kuantitas stok tidak valid.";
        header("Location: ../workshop-stok.php");
        exit;
    }

    // Eksekusi pembaruan kuantitas mutlak berdasarkan ID Baris Stok Workshop
    $sql  = "UPDATE stok_sparepart_wk SET stok = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $stokBaru, $stokId);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['form_success'] = "Kuantitas fisik stok workshop berhasil diperbarui menjadi " . $stokBaru;
    } else {
        $_SESSION['form_error'] = "Sistem gagal memperbarui data database. Silakan coba lagi.";
    }
}

header("Location: ../workshop-stok.php");
exit;