<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pengiriman.php');
    exit;
}

$tujuanUserId = $_POST['user_id'] ?? null;
$rawItems     = $_POST['items'] ?? [];

if (empty($tujuanUserId)) {
    $_SESSION['form_error'] = 'Workshop tujuan wajib dipilih.';
    header('Location: create-pengiriman.php');
    exit;
}

// Pastikan tujuan benar-benar kepala workshop
$checkUser = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'kepala workshop'");
mysqli_stmt_bind_param($checkUser, 'i', $tujuanUserId);
mysqli_stmt_execute($checkUser);
$userExists = mysqli_stmt_get_result($checkUser);

if (!mysqli_fetch_assoc($userExists)) {
    $_SESSION['form_error'] = 'Workshop tujuan tidak valid.';
    header('Location: create-pengiriman.php');
    exit;
}

// ===== Susun & validasi daftar item sparepart =====
$items = [];

foreach ($rawItems as $row) {
    $sparepartId = $row['sparepart_id'] ?? null;
    $quantity    = $row['quantity'] ?? null;

    if (empty($sparepartId) && empty($quantity)) {
        continue;
    }

    if (empty($sparepartId) || empty($quantity)) {
        $_SESSION['form_error'] = 'Setiap baris harus memiliki sparepart dan jumlah.';
        header('Location: create-pengiriman.php');
        exit;
    }

    if (!is_numeric($quantity) || $quantity <= 0) {
        $_SESSION['form_error'] = 'Jumlah harus berupa angka lebih dari 0.';
        header('Location: create-pengiriman.php');
        exit;
    }

    $items[] = ['sparepart_id' => (int) $sparepartId, 'quantity' => (int) $quantity];
}

if (empty($items)) {
    $_SESSION['form_error'] = 'Minimal satu sparepart harus dipilih.';
    header('Location: create-pengiriman.php');
    exit;
}

// Cek duplikat sparepart dalam satu submit
$idsOnly = array_column($items, 'sparepart_id');
if (count($idsOnly) !== count(array_unique($idsOnly))) {
    $_SESSION['form_error'] = 'Tidak boleh memilih sparepart yang sama lebih dari sekali.';
    header('Location: create-pengiriman.php');
    exit;
}

// ===== Validasi stok cukup di warehouse untuk semua item =====
$stokKurang = [];

foreach ($items as $item) {

    $checkStok = mysqli_prepare($conn, "
        SELECT w.stok, s.nama_sparepart
        FROM stok_sparepart_wr w
        JOIN sparepart s ON s.id = w.sparepart_id
        WHERE w.sparepart_id = ?
    ");
    mysqli_stmt_bind_param($checkStok, 'i', $item['sparepart_id']);
    mysqli_stmt_execute($checkStok);
    $stokResult = mysqli_stmt_get_result($checkStok);
    $stokData = mysqli_fetch_assoc($stokResult);

    $stokTersedia  = $stokData['stok'] ?? 0;
    $namaSparepart = $stokData['nama_sparepart'] ?? "Sparepart ID {$item['sparepart_id']}";

    if ($item['quantity'] > $stokTersedia) {
        $stokKurang[] = "(tersedia: $stokTersedia, minimal: {$item['quantity']})";
    }
}

if (!empty($stokKurang)) {
    $_SESSION['form_error'] = 'Stok tidak cukup untuk: ' . implode(', ', $stokKurang) . '.';
    header('Location: create-pengiriman.php');
    exit;
}

// ===== Generate nomor surat otomatis: SJ-2026-001, dst =====
$tahunSekarang = date('Y');

$countSurat = mysqli_query($conn, "SELECT COUNT(*) as total FROM surat_jalan WHERE nomor_surat LIKE 'SJ-$tahunSekarang-%'");
$totalSurat = mysqli_fetch_assoc($countSurat)['total'];
$nomorUrut  = str_pad($totalSurat + 1, 3, '0', STR_PAD_LEFT);
$nomorSurat = "SJ-$tahunSekarang-$nomorUrut";

// ===== Proses dalam SATU transaction: insert surat_jalan + surat_jalan_detail =====
mysqli_begin_transaction($conn);

try {

    // 1. Insert surat_jalan (header) — status draft, belum dikirim ke workshop
    $insertSurat = mysqli_prepare($conn, "
        INSERT INTO surat_jalan (user_id, nomor_surat, status, tanggal_kirim)
        VALUES (?, ?, 'draft', CURDATE())
    ");
    mysqli_stmt_bind_param($insertSurat, 'is', $tujuanUserId, $nomorSurat);

    if (!mysqli_stmt_execute($insertSurat)) {
        throw new Exception('Gagal membuat data pengiriman: ' . mysqli_error($conn));
    }

    $suratJalanId = mysqli_insert_id($conn);

    // 2. Insert detail untuk setiap sparepart yang dipilih
    foreach ($items as $item) {
        $insertDetail = mysqli_prepare($conn, "
            INSERT INTO surat_jalan_detail (surat_jalan_id, sparepart_id, quantity)
            VALUES (?, ?, ?)
        ");
        mysqli_stmt_bind_param($insertDetail, 'iii', $suratJalanId, $item['sparepart_id'], $item['quantity']);

        if (!mysqli_stmt_execute($insertDetail)) {
            throw new Exception('Gagal menyimpan detail pengiriman: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    $jumlahItem = count($items);
    $_SESSION['form_success'] = "Pengiriman \"$nomorSurat\" berhasil dibuat dengan $jumlahItem sparepart. Klik \"Kirim Surat\" untuk mengirimkannya ke workshop.";
    header('Location: ../daftar-surat.php');

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage();
    header('Location: create-pengiriman.php');
}
exit;