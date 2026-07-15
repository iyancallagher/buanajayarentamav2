<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../daftar-sparepart.php');
    exit;
}

$pengajuanIds = $_POST['pengajuan_ids'] ?? [];

if (empty($pengajuanIds)) {
    $_SESSION['form_error'] = 'Pilih minimal satu sparepart untuk dicetak.';
    header('Location: ../daftar-sparepart.php');
    exit;
}

// Pastikan semua ID berupa integer (mencegah injection lewat array)
$pengajuanIds = array_map('intval', $pengajuanIds);
$placeholders = implode(',', array_fill(0, count($pengajuanIds), '?'));
$types        = str_repeat('i', count($pengajuanIds));

// ===== Ambil data pengajuan terpilih, sekaligus validasi status & user_id =====
$sql = "
    SELECT p.id, p.sparepart_id, p.quantity, p.user_id, p.status, p.surat_jalan_id
    FROM pengajuan_sparepart p
    WHERE p.id IN ($placeholders)
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$pengajuanIds);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pengajuanData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pengajuanData[] = $row;
}

// Validasi: jumlah data yang ditemukan harus sama dengan jumlah ID yang dikirim
if (count($pengajuanData) !== count($pengajuanIds)) {
    $_SESSION['form_error'] = 'Beberapa pengajuan tidak ditemukan. Silakan coba lagi.';
    header('Location: ../pengajuan-sparepart.php');
    exit;
}

// Validasi: semua harus status 'setuju' DAN belum pernah dicetak (surat_jalan_id masih NULL)
foreach ($pengajuanData as $row) {
    if ($row['status'] !== 'setuju') {
        $_SESSION['form_error'] = 'Hanya pengajuan dengan status disetujui yang bisa dicetak.';
        header('Location: ../pengajuan-sparepart.php');
        exit;
    }

    if (!empty($row['surat_jalan_id'])) {
        $_SESSION['form_error'] = 'Salah satu pengajuan sudah pernah dicetak sebelumnya.';
        header('Location: ../pengajuan-sparepart.php');
        exit;
    }
}

// ===== VALIDASI UTAMA: semua user_id harus sama =====
$uniqueUserIds = array_unique(array_column($pengajuanData, 'user_id'));

if (count($uniqueUserIds) > 1) {
    $_SESSION['form_error'] = 'Tidak bisa membuat satu surat untuk pengajuan dari user yang berbeda.';
    header('Location: ../pengajuan-sparepart.php');
    exit;
}

$userId = reset($uniqueUserIds);

// ===== Generate nomor surat SJ-2026-001, SJ-2026-002 =====
$tahunSekarang = date('Y');

$countSurat = mysqli_query($conn, "SELECT COUNT(*) as total FROM surat_jalan WHERE nomor_surat LIKE 'SJ-$tahunSekarang-%'");
$totalSurat = mysqli_fetch_assoc($countSurat)['total'];
$nomorUrut  = str_pad($totalSurat + 1, 3, '0', STR_PAD_LEFT);
$nomorSurat = "SJ-$tahunSekarang-$nomorUrut";

// ===== Mulai transaction: insert surat_jalan + surat_jalan_detail + tandai pengajuan =====
mysqli_begin_transaction($conn);

try {

    // 1. Insert surat_jalan (header)
    $insertSurat = mysqli_prepare($conn, "
        INSERT INTO surat_jalan (user_id, nomor_surat, tanggal_kirim, status)
        VALUES (?, ?, CURDATE(), 'draft')
    ");
    mysqli_stmt_bind_param($insertSurat, 'is', $userId, $nomorSurat);

    if (!mysqli_stmt_execute($insertSurat)) {
        throw new Exception('Gagal membuat pengiriman: ' . mysqli_error($conn));
    }

    $suratJalanId = mysqli_insert_id($conn);

    //Insert detail untuk setiap sparepart terpilih + tandai pengajuan sebagai sudah dicetak
    foreach ($pengajuanData as $row) {

        $insertDetail = mysqli_prepare($conn, "
            INSERT INTO surat_jalan_detail (surat_jalan_id, sparepart_id, quantity)
            VALUES (?, ?, ?)
        ");
        mysqli_stmt_bind_param($insertDetail, 'iii', $suratJalanId, $row['sparepart_id'], $row['quantity']);

        if (!mysqli_stmt_execute($insertDetail)) {
            throw new Exception('Gagal menyimpan detail pengiriman: ' . mysqli_error($conn));
        }

        // Tandai pengajuan ini sudah masuk ke pengiriman, supaya tidak bisa dicetak dobel
        $updatePengajuan = mysqli_prepare($conn, "
            UPDATE pengajuan_sparepart SET surat_jalan_id = ? WHERE id = ?
        ");
        mysqli_stmt_bind_param($updatePengajuan, 'ii', $suratJalanId, $row['id']);

        if (!mysqli_stmt_execute($updatePengajuan)) {
            throw new Exception('Gagal menandai pengajuan sebagai tercetak: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    $_SESSION['form_success'] = "Pengiriman \"$nomorSurat\" berhasil dibuat dengan " . count($pengajuanData) . " item.";
    header('Location: ../daftar-sparepart.php');

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage();
    header('Location: ../daftar-sparepart.php');
}
exit;