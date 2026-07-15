<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../maintenance.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$typeUnit = trim($_POST['type_unit'] ?? '');
$nopol    = trim($_POST['nopol'] ?? '');
$mekanik  = trim($_POST['mekanik'] ?? '');
$rawItems = $_POST['items'] ?? [];

// ===== Validasi field utama =====
if (empty($typeUnit) || empty($nopol) || empty($mekanik)) {
    $_SESSION['form_error'] = 'Tipe unit, nomor polisi, dan nama mekanik wajib diisi.';
    header('Location: create-maintenance.php');
    exit;
}

// ===== Susun & validasi daftar item sparepart =====
$items = [];

foreach ($rawItems as $row) {
    $sparepartId = $row['sparepart_id'] ?? null;
    $quantity    = $row['quantity'] ?? null;

    if (empty($sparepartId) && empty($quantity)) {
        continue; // baris kosong, lewati
    }

    if (empty($sparepartId) || empty($quantity)) {
        $_SESSION['form_error'] = 'Setiap baris sparepart harus memiliki sparepart dan jumlah.';
        header('Location: create-maintenance.php');
        exit;
    }

    if (!is_numeric($quantity) || $quantity <= 0) {
        $_SESSION['form_error'] = 'Jumlah harus berupa angka lebih dari 0.';
        header('Location: create-maintenance.php');
        exit;
    }

    $items[] = ['sparepart_id' => (int) $sparepartId, 'quantity' => (int) $quantity];
}

if (empty($items)) {
    $_SESSION['form_error'] = 'Minimal satu sparepart harus dipilih.';
    header('Location: create-maintenance.php');
    exit;
}

// Pastikan tidak ada sparepart_id yang duplikat dalam satu submit
$idsOnly = array_column($items, 'sparepart_id');
if (count($idsOnly) !== count(array_unique($idsOnly))) {
    $_SESSION['form_error'] = 'Tidak boleh memilih sparepart yang sama lebih dari sekali.';
    header('Location: create-maintenance.php');
    exit;
}

// ===== Validasi stok cukup untuk SEMUA item, sebelum proses apapun =====
$stokKurang = [];

foreach ($items as $item) {

    $checkStok = mysqli_prepare($conn, "
        SELECT sw.stok, s.nama_sparepart
        FROM stok_sparepart_wk sw
        JOIN sparepart s ON s.id = sw.sparepart_id
        WHERE sw.user_id = ? AND sw.sparepart_id = ?
    ");
    mysqli_stmt_bind_param($checkStok, 'ii', $userId, $item['sparepart_id']);
    mysqli_stmt_execute($checkStok);
    $stokResult = mysqli_stmt_get_result($checkStok);
    $stokData = mysqli_fetch_assoc($stokResult);

    if (!$stokData) {
        $stokKurang[] = "Sparepart ID {$item['sparepart_id']} tidak ditemukan di stok kamu";
        continue;
    }

    if ($item['quantity'] > $stokData['stok']) {
        $stokKurang[] = $stokData['nama_sparepart'] . " (tersedia: {$stokData['stok']}, dibutuhkan: {$item['quantity']})";
    }
}

if (!empty($stokKurang)) {
    $_SESSION['form_error'] = 'Stok tidak cukup untuk: ' . implode(', ', $stokKurang) . '.';
    header('Location: create-maintenance.php');
    exit;
}

// ===== Semua valid, proses dalam SATU transaction =====
mysqli_begin_transaction($conn);

try {

    // 1. Insert ke maintenance_wk, sparepart_list disimpan sebagai JSON
    $sparepartListJson = json_encode($items);

    $insertMaintenance = mysqli_prepare($conn, "
        INSERT INTO maintenance_wk (type_unit, nopol, sparepart_list, mekanik, user_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($insertMaintenance, 'ssssi', $typeUnit, $nopol, $sparepartListJson, $mekanik, $userId);

    if (!mysqli_stmt_execute($insertMaintenance)) {
        throw new Exception('Gagal menyimpan data maintenance: ' . mysqli_error($conn));
    }

    // 2. Kurangi stok untuk setiap item
    foreach ($items as $item) {

        $updateStok = mysqli_prepare($conn, "
            UPDATE stok_sparepart_wk
            SET stok = stok - ?
            WHERE user_id = ? AND sparepart_id = ?
        ");
        mysqli_stmt_bind_param($updateStok, 'iii', $item['quantity'], $userId, $item['sparepart_id']);

        if (!mysqli_stmt_execute($updateStok)) {
            throw new Exception('Gagal mengurangi stok untuk sparepart ID ' . $item['sparepart_id'] . ': ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    $jumlahItem = count($items);
    $_SESSION['form_success'] = "Maintenance untuk unit \"$typeUnit ($nopol)\" oleh $mekanik berhasil dicatat dengan $jumlahItem sparepart, stok telah dikurangi.";
    header('Location: ../maintenance.php');

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage();
    header('Location: create-maintenance.php');
}
exit;