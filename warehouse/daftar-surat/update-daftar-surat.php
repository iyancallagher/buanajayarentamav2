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
// Tangkap tanggal pengiriman susulan dari input form
$tanggal_susulan = $_POST['tanggal_susulan'] ?? ''; 

if ($surat_id <= 0) {
    $_SESSION['form_error'] = "Parameter surat jalan tidak valid.";
    header("Location: ../daftar-surat.php");
    exit;
}

// Validasi tambahan: Tanggal susulan wajib diisi jika ingin melakukan pengiriman susulan
if (empty($tanggal_susulan)) {
    $_SESSION['form_error'] = "Tanggal pengiriman susulan wajib diisi.";
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

    // MODIFIKASI QUERY:
    // Selain mengubah status menjadi 'dikirim' dan mereset 'tanggal_terima' menjadi NULL,
    // kita juga menyimpan tanggal pengiriman susulan ke dalam kolom 'tanggal_susulan'
    $querySurat = "UPDATE surat_jalan SET status = 'dikirim', tanggal_terima = NULL, tanggal_susulan = ? WHERE id = ?";
    $stmtSurat  = mysqli_prepare($conn, $querySurat);
    
    // Bind parameter tanggal (s) dan id (i)
    mysqli_stmt_bind_param($stmtSurat, "si", $tanggal_susulan, $surat_id);
    
    if (!mysqli_stmt_execute($stmtSurat)) {
        throw new Exception("Gagal memperbarui status dokumen ke database.");
    }

    mysqli_commit($conn);
    
    // Ubah format tanggal ke format Indonesia agar info alert lebih rapi (d M Y)
    $tanggal_tampil = date('d M Y', strtotime($tanggal_susulan));
    $_SESSION['form_success'] = "Status surat jalan \"{$surat['nomor_surat']}\" berhasil diubah menjadi [Dikirim] kembali untuk pengiriman susulan pada tanggal {$tanggal_tampil}.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = "Gagal memproses pengiriman susulan: " . $e->getMessage();
}

// 6. KEMBALI KE HALAMAN DAFTAR SURAT
header("Location: ../daftar-surat.php");
exit;