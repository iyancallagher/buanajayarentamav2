<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../daftar-surat.php');
    exit;
}

$suratId = $_POST['surat_id'] ?? null;

if (empty($suratId)) {
    $_SESSION['form_error'] = 'Surat jalan tidak ditemukan.';
    header('Location: ../daftar-surat.php');
    exit;
}

// Pastikan surat ada dan statusnya masih 'draft' (mencegah kirim dobel)
// Sekalian ambil user_id penerima dari surat ini
$check = mysqli_prepare($conn, "SELECT id, nomor_surat, status, user_id FROM surat_jalan WHERE id = ?");
mysqli_stmt_bind_param($check, 'i', $suratId);
mysqli_stmt_execute($check);
$result = mysqli_stmt_get_result($check);
$surat = mysqli_fetch_assoc($result);

if (!$surat) {
    $_SESSION['form_error'] = 'Surat jalan tidak ditemukan.';
    header('Location: ../daftar-surat.php');
    exit;
}

if ($surat['status'] !== 'draft') {
    $_SESSION['form_error'] = 'Surat jalan ini sudah dikirim sebelumnya.';
    header('Location: ../daftar-surat.php');
    exit;
}

$penerimaUserId = $surat['user_id'];

// ===== Ambil semua item di surat ini, sekaligus cek stok saat ini =====
$detailSql = "
    SELECT sjd.sparepart_id, sjd.quantity,
           s.kode_sparepart, s.nama_sparepart,
           IFNULL(w.stok, 0) as stok_tersedia
    FROM surat_jalan_detail sjd
    JOIN sparepart s ON s.id = sjd.sparepart_id
    LEFT JOIN stok_sparepart_wr w ON w.sparepart_id = sjd.sparepart_id
    WHERE sjd.surat_jalan_id = ?
";

$detailStmt = mysqli_prepare($conn, $detailSql);
mysqli_stmt_bind_param($detailStmt, 'i', $suratId);
mysqli_stmt_execute($detailStmt);
$detailResult = mysqli_stmt_get_result($detailStmt);

$items = [];
while ($row = mysqli_fetch_assoc($detailResult)) {
    $items[] = $row;
}

if (empty($items)) {
    $_SESSION['form_error'] = 'Surat jalan ini tidak memiliki item sparepart.';
    header('Location: ../daftar-surat.php');
    exit;
}

// ===== VALIDASI STOK: cek SEMUA item dulu sebelum proses apapun =====
$stokKurang = [];

foreach ($items as $item) {
    if ($item['quantity'] > $item['stok_tersedia']) {
        $stokKurang[] = $item['nama_sparepart'] . " (tersedia: {$item['stok_tersedia']}, dibutuhkan: {$item['quantity']})";
    }
}

if (!empty($stokKurang)) {
    $_SESSION['form_error'] = 'Stok tidak cukup untuk: ' . implode(', ', $stokKurang) . '. Surat jalan tidak dapat dikirim.';
    header('Location: ../daftar-surat.php');
    exit;
}

// ===== Semua stok cukup, lanjut proses: kurangi stok + catat keluar + update status, dalam SATU transaction =====
mysqli_begin_transaction($conn);

try {

    foreach ($items as $item) {

        // 1. Kurangi stok
        $updateStok = mysqli_prepare($conn, "
            UPDATE stok_sparepart_wr
            SET stok = stok - ?
            WHERE sparepart_id = ?
        ");
        mysqli_stmt_bind_param($updateStok, 'ii', $item['quantity'], $item['sparepart_id']);

        if (!mysqli_stmt_execute($updateStok)) {
            throw new Exception('Gagal mengurangi stok untuk ' . $item['nama_sparepart'] );
        }

        // 2. Catat ke riwayat sparepart_keluar_wr, sertakan user_id penerima
        $insertKeluar = mysqli_prepare($conn, "
            INSERT INTO sparepart_keluar_wr (sparepart_id, user_id, quantity)
            VALUES (?, ?, ?)
        ");
        mysqli_stmt_bind_param($insertKeluar, 'iii', $item['sparepart_id'], $penerimaUserId, $item['quantity']);

        if (!mysqli_stmt_execute($insertKeluar)) {
            throw new Exception('Gagal mencatat riwayat sparepart keluar untuk ' . $item['nama_sparepart'] . ': ' . mysqli_error($conn));
        }
    }

    // 3. Update status surat jadi 'dikirim'
    $updateSurat = mysqli_prepare($conn, "UPDATE surat_jalan SET status = 'dikirim' WHERE id = ?");
    mysqli_stmt_bind_param($updateSurat, 'i', $suratId);

    if (!mysqli_stmt_execute($updateSurat)) {
        throw new Exception('Gagal mengubah status surat jalan: ' . mysqli_error($conn));
    }

    mysqli_commit($conn);

    $_SESSION['form_success'] = "Surat jalan \"{$surat['nomor_surat']}\" berhasil dikirim, stok telah dikurangi, dan riwayat sparepart keluar telah dicatat.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage() . ' Status surat tetap draft.';
}

header('Location: ../daftar-surat.php');
exit;