<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sparepart-masuk.php');
    exit;
}

// ===== Susun daftar item yang akan diproses dari items[index][sparepart_id/quantity] =====
$items = [];
$rawItems = $_POST['items'] ?? [];

foreach ($rawItems as $row) {
    $sparepartId = $row['sparepart_id'] ?? null;
    $quantity    = $row['quantity'] ?? null;

    // Lewati baris yang benar-benar kosong (user tambah baris tapi tidak diisi)
    if (empty($sparepartId) && empty($quantity)) {
        continue;
    }

    // Tapi kalau salah satu diisi dan satunya kosong, itu error
    if (empty($sparepartId) || empty($quantity)) {
        $_SESSION['form_error'] = 'Setiap baris yang diisi harus memiliki sparepart dan jumlah.';
        header('Location: create-sparepart-masuk.php');
        exit;
    }

    $items[] = ['sparepart_id' => $sparepartId, 'quantity' => $quantity];
}

if (empty($items)) {
    $_SESSION['form_error'] = 'Minimal satu item harus diisi.';
    header('Location: create-sparepart-masuk.php');
    exit;
}

// ===== Validasi tiap item: quantity harus angka positif, sparepart_id harus valid =====
foreach ($items as $item) {
    if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
        $_SESSION['form_error'] = 'Jumlah harus berupa angka lebih dari 0.';
        header('Location: create-sparepart-masuk.php');
        exit;
    }

    $checkSparepart = mysqli_prepare($conn, "SELECT id FROM sparepart WHERE id = ?");
    mysqli_stmt_bind_param($checkSparepart, 'i', $item['sparepart_id']);
    mysqli_stmt_execute($checkSparepart);
    $sparepartExists = mysqli_stmt_get_result($checkSparepart);

    if (!mysqli_fetch_assoc($sparepartExists)) {
        $_SESSION['form_error'] = 'Salah satu sparepart yang dipilih tidak ditemukan.';
        header('Location: create-sparepart-masuk.php');
        exit;
    }
}

// ===== Proses semua item dalam SATU transaction =====
mysqli_begin_transaction($conn);

try {

    foreach ($items as $item) {
        $sparepartId = $item['sparepart_id'];
        $quantity    = $item['quantity'];

        // 1. Insert ke riwayat sparepart_masuk_wr
        $insertMasuk = mysqli_prepare($conn, "INSERT INTO sparepart_masuk_wr (sparepart_id, quantity) VALUES (?, ?)");
        mysqli_stmt_bind_param($insertMasuk, 'ii', $sparepartId, $quantity);

        if (!mysqli_stmt_execute($insertMasuk)) {
            throw new Exception('Gagal menyimpan riwayat sparepart masuk: ' . mysqli_error($conn));
        }

        // 2. Cek dan update/insert stok_sparepart_wr
        $checkStok = mysqli_prepare($conn, "SELECT id FROM stok_sparepart_wr WHERE sparepart_id = ?");
        mysqli_stmt_bind_param($checkStok, 'i', $sparepartId);
        mysqli_stmt_execute($checkStok);
        $stokResult = mysqli_stmt_get_result($checkStok);
        $stokData = mysqli_fetch_assoc($stokResult);

        if ($stokData) {
            $updateStok = mysqli_prepare($conn, "UPDATE stok_sparepart_wr SET stok = stok + ? WHERE sparepart_id = ?");
            mysqli_stmt_bind_param($updateStok, 'ii', $quantity, $sparepartId);

            if (!mysqli_stmt_execute($updateStok)) {
                throw new Exception('Gagal memperbarui stok: ' . mysqli_error($conn));
            }
        } else {
            $insertStok = mysqli_prepare($conn, "INSERT INTO stok_sparepart_wr (sparepart_id, stok, minimal_stok) VALUES (?, ?, 0)");
            mysqli_stmt_bind_param($insertStok, 'ii', $sparepartId, $quantity);

            if (!mysqli_stmt_execute($insertStok)) {
                throw new Exception('Gagal membuat data stok baru: ' . mysqli_error($conn));
            }
        }
    }

    // Semua item berhasil, commit
    mysqli_commit($conn);

    $totalItems = count($items);
    $_SESSION['form_success'] = $totalItems > 1
        ? "$totalItems sparepart masuk berhasil dicatat dan stok telah diperbarui."
        : 'Sparepart masuk berhasil dicatat dan stok telah diperbarui.';

    header('Location: ../sparepart-masuk.php');

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage();
    header('Location: create-sparepart-masuk.php');
}
exit;