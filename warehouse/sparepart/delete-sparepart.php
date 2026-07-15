<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

// Validasi jika akses langsung (wajib lewat POST dari modal konfirmasi)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sparepart.php');
    exit;
}

// Ambil ID dari parameter URL (?id=...)
$id = trim($_GET['id'] ?? '');

if (empty($id)) {
    $_SESSION['form_error'] = 'ID Sparepart tidak valid atau tidak ditemukan!';
    header('Location: ../sparepart.php');
    exit;
}

// 1. Cek terlebih dahulu apakah data sparepart-nya memang ada di database
$checkSql = "SELECT nama_sparepart FROM sparepart WHERE id = ? AND deleted_at IS NULL";
$checkStmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($checkStmt, 'i', $id);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$sparepart = mysqli_fetch_assoc($checkResult);

if (!$sparepart) {
    $_SESSION['form_error'] = 'Data sparepart sudah tidak ada atau telah dihapus sebelumnya.';
    header('Location: ../sparepart.php');
    exit;
}

// 2. Cek apakah sparepart ini masih memiliki stok di gudang (WK) maupun workshop (WR)
$stokSql = "
    SELECT
        (SELECT COALESCE(SUM(stok), 0) FROM stok_sparepart_wk WHERE sparepart_id = ?) as stok_wk,
        (SELECT COALESCE(SUM(stok), 0) FROM stok_sparepart_wr WHERE sparepart_id = ?) as stok_wr
";
$stokStmt = mysqli_prepare($conn, $stokSql);
mysqli_stmt_bind_param($stokStmt, 'ii', $id, $id);
mysqli_stmt_execute($stokStmt);
$stokResult = mysqli_stmt_get_result($stokStmt);
$stokData = mysqli_fetch_assoc($stokResult);

$stokWk = (int) $stokData['stok_wk'];
$stokWr = (int) $stokData['stok_wr'];

if ($stokWk > 0 || $stokWr > 0) {
    $lokasi = [];
    if ($stokWk > 0) {
        $lokasi[] = 'Gudang (' . $stokWk . ' unit)';
    }
    if ($stokWr > 0) {
        $lokasi[] = 'Workshop (' . $stokWr . ' unit)';
    }

    $_SESSION['form_error'] = 'Sparepart "' . htmlspecialchars($sparepart['nama_sparepart']) . '" tidak dapat dihapus karena masih memiliki stok di ' . implode(' dan ', $lokasi) . '. Kosongkan stok terlebih dahulu sebelum menghapus.';
    header('Location: ../sparepart.php');
    exit;
}

// 3. Eksekusi soft delete (set deleted_at) jika data ditemukan dan tidak ada stok tersisa
$deleteSql = "UPDATE sparepart SET deleted_at = NOW() WHERE id = ?";
$deleteStmt = mysqli_prepare($conn, $deleteSql);

if ($deleteStmt) {
    mysqli_stmt_bind_param($deleteStmt, 'i', $id);

    if (mysqli_stmt_execute($deleteStmt)) {
        // Set flash message sukses dengan menyertakan nama sparepart yang dihapus
        $_SESSION['form_success'] = 'Sparepart "' . htmlspecialchars($sparepart['nama_sparepart']) . '" berhasil dihapus.';
        header('Location: ../sparepart.php');
        exit;
    }
}

// Handler jika query ke database gagal
$_SESSION['form_error'] = 'Gagal menghapus data dari sistem database.';
header('Location: ../sparepart.php');
exit;