<?php
require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = $_POST['status'] ?? '';
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : null;
    
    if ($id <= 0 || !in_array($status, ['setuju', 'tolak'])) {
        header("Location: ../pengajuan.php?status=error");
        exit;
    }

    if ($status === 'setuju') {
        // Jika disetujui, ambil nilai quantity dari input manajer
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        $updateSql = "UPDATE pengajuan_sparepart SET status = ?, quantity = ?, keterangan = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'sisi', $status, $quantity, $keterangan, $id);
    } else {
        // Jika ditolak, status berubah tanpa mengubah nilai kuantitas awal
        $updateSql = "UPDATE pengajuan_sparepart SET status = ?, keterangan = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $keterangan, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        header("Location: ../pengajuan-sparepart.php?status=success");
    } else {
        header("Location: ../pengajuan-sparepart.php?status=error");
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
} else {
    header("Location: ../pengajuan-sparepart.php");
    exit;
}