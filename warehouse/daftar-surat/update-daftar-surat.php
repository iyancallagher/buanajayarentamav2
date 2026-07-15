<?php
session_start();

// 1. PENGAMAN HAK AKSES
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

// 2. KONEKSI DATABASE
require_once '../../config/database.php';

// 3. VALIDASI METODE REQUEST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../daftar-surat.php");
    exit;
}

// 4. TANGKAP PARAMETER UTAMA
$surat_id = (int)($_POST['surat_id'] ?? 0);

if ($surat_id <= 0) {
    $_SESSION['form_error'] = "Parameter surat jalan tidak valid.";
    header("Location: ../daftar-surat.php");
    exit;
}

// 5. PROSES UBAH STATUS MENGGUNAKAN TRANSACTION DATABASE
mysqli_begin_transaction($conn);

try {
    // Memastikan data surat jalan ada di database (Kunci baris untuk keamanan data)
    $checkSql = "SELECT id, nomor_surat, status FROM surat_jalan WHERE id = ? FOR UPDATE";
    $stmtCheck = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($stmtCheck, 'i', $surat_id);
    mysqli_stmt_execute($stmtCheck);
    $surat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCheck));

    if (!$surat) {
        throw new Exception("Surat jalan tidak ditemukan.");
    }

    // Eksekusi pembaruan status header surat jalan menjadi 'dikirim' kembali
    // Menghapus isi kolom 'tanggal_terima' agar form konfirmasi di workshop aktif lagi
    // Data kuantitas dikirim & diterima tidak disentuh/dihapus sama sekali
    $querySurat = "UPDATE surat_jalan SET status = 'dikirim', tanggal_terima = NULL WHERE id = ?";
    $stmtSurat  = mysqli_prepare($conn, $querySurat);
    mysqli_stmt_bind_param($stmtSurat, "i", $surat_id);
    
    if (!mysqli_stmt_execute($stmtSurat)) {
        throw new Exception("Gagal memperbarui status dokumen ke database.");
    }

    mysqli_commit($conn);
    $_SESSION['form_success'] = "Status surat jalan \"{$surat['nomor_surat']}\" berhasil diubah menjadi [Dikirim] kembali untuk pengiriman susulan.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = "Gagal memproses pengiriman susulan: " . $e->getMessage();
}

// 6. KEMBALI KE HALAMAN DAFTAR SURAT
header("Location: ../daftar-surat.php");
exit;