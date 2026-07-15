<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

// Pastikan request datang dari method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../sparepart-stok.php");
    exit;
}

$action       = $_POST['action'] ?? '';
$sparepart_id = (int)($_POST['sparepart_id'] ?? 0);
$stok_id      = (int)($_POST['stok_id'] ?? 0);

// Validasi dasar ID Sparepart
if ($sparepart_id <= 0) {
    $_SESSION['form_error'] = "ID Sparepart tidak valid atau tidak ditemukan.";
    header("Location: ../sparepart-stok.php");
    exit;
}

// Antisipasi jika stok_id dilempar 0, lakukan cek ulang ke database
if ($stok_id === 0) {
    $checkQuery = "SELECT id FROM stok_sparepart_wr WHERE sparepart_id = ?";
    $stmtCheck  = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmtCheck, "i", $sparepart_id);
    mysqli_stmt_execute($stmtCheck);
    $resCheck = mysqli_stmt_get_result($stmtCheck);
    if ($rowCheck = mysqli_fetch_assoc($resCheck)) {
        $stok_id = (int)$rowCheck['id'];
    }
}

// ==========================================
// PROSES UPDATE / INSERT PARAMETER STOK (Batas Minimum + Koreksi Stok, sekaligus)
// ==========================================
if ($action === 'update_parameter') {
    $minimal_stok = max(0, (int)($_POST['minimal_stok'] ?? 0));
    $stok         = max(0, (int)($_POST['stok'] ?? 0));

    if ($stok_id > 0) {
        // Jika data stok sudah ada di gudang, lakukan UPDATE kedua kolom sekaligus + catat waktu edit
        $query = "UPDATE stok_sparepart_wr SET minimal_stok = ?, stok = ?, updated_at = NOW() WHERE id = ?";
        $stmt  = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iii", $minimal_stok, $stok, $stok_id);
    } else {
        // Jika sparepart baru dan belum ada record di tabel stok, lakukan INSERT
        $query = "INSERT INTO stok_sparepart_wr (sparepart_id, minimal_stok, stok, updated_at) VALUES (?, ?, ?, NOW())";
        $stmt  = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iii", $sparepart_id, $minimal_stok, $stok);
    }

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['form_success'] = "Parameter stok (batas minimum dan jumlah stok) berhasil diperbarui.";
    } else {
        $_SESSION['form_error'] = "Gagal memperbarui parameter stok di database.";
    }
} else {
    $_SESSION['form_error'] = "Aksi manajemen stok tidak dikenali.";
}

// Alihkan kembali ke halaman monitoring utama
header("Location: ../sparepart-stok.php");
exit;