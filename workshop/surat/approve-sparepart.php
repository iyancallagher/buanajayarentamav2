<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../surat-pengiriman.php');
    exit;
}

$userId  = $_SESSION['user_id'];
$suratId = $_POST['surat_id'] ?? null;
$qtyDiterimaInput = $_POST['qty_diterima'] ?? []; // Array [detail_id => value] dari form

if (empty($suratId)) {
    $_SESSION['form_error'] = 'Surat jalan tidak ditemukan.';
    header('Location: ../surat-pengiriman.php');
    exit;
}

// Proses dalam SATU transaction dengan penguncian baris dokumen utama
mysqli_begin_transaction($conn);

try {
    // Pastikan surat ada, milik user ini, dan statusnya masih 'dikirim'
    $check = mysqli_prepare($conn, "
        SELECT id, nomor_surat, status, user_id
        FROM surat_jalan
        WHERE id = ? FOR UPDATE
    ");
    mysqli_stmt_bind_param($check, 'i', $suratId);
    mysqli_stmt_execute($check);
    $result = mysqli_stmt_get_result($check);
    $surat = mysqli_fetch_assoc($result);

    if (!$surat) {
        throw new Exception('Surat jalan tidak ditemukan.');
    }

    if ($surat['user_id'] != $userId) {
        throw new Exception('Surat jalan ini bukan milik kamu.');
    }

    if ($surat['status'] !== 'dikirim') {
        throw new Exception('Surat jalan ini sudah diterima sebelumnya atau belum dikirim kembali oleh gudang.');
    }

    // ===== Ambil semua item di surat ini beserta kolom quantity_diterima versi lama =====
    $detailSql = "
        SELECT sjd.id AS detail_id, sjd.sparepart_id, sjd.quantity, sjd.quantity_diterima, s.nama_sparepart
        FROM surat_jalan_detail sjd
        JOIN sparepart s ON s.id = sjd.sparepart_id
        WHERE sjd.surat_jalan_id = ? FOR UPDATE
    ";

    $detailStmt = mysqli_prepare($conn, $detailSql);
    mysqli_stmt_bind_param($detailStmt, 'i', $suratId);
    mysqli_stmt_execute($detailStmt);
    $detailResult = mysqli_stmt_get_result($detailStmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($detailResult)) {
        $items[$row['detail_id']] = $row;
    }

    if (empty($items)) {
        throw new Exception('Surat jalan ini tidak memiliki item sparepart.');
    }

    $totalItems = count($items);
    $matchingItems = 0;

    foreach ($items as $detailId => $item) {
        // Ambil nilai qty baru yang diinput user. Jika kosong atau tidak terkirim, default ke 0
        $qtyTerimaBaru = isset($qtyDiterimaInput[$detailId]) ? (int)$qtyDiterimaInput[$detailId] : 0;
        $qtyKirim      = (int)$item['quantity'];
        
        // Ambil riwayat kuantitas lama yang sudah terlanjur dikonfirmasi pada gelombang sebelumnya
        $qtyTerimaLama = $item['quantity_diterima'] !== null ? (int)$item['quantity_diterima'] : 0;

        // Proteksi batas input kuantitas
        if ($qtyTerimaBaru < 0 || $qtyTerimaBaru > $qtyKirim) {
            throw new Exception('Jumlah diterima untuk ' . $item['nama_sparepart'] . ' tidak valid.');
        }

        // HITUNG TAMBAHAN FISIK BARANG DARI GELOMBANG SUSULAN
        // Contoh: Input Akumulasi Baru (10) - Riwayat Lama (8) = +2 pcs tambahan stok riil
        $tambahanStokReal = $qtyTerimaBaru - $qtyTerimaLama;

        // Jika user sengaja menurunkan angka inputan di bawah riwayat lama, cegah agar stok workshop tidak rusak
        if ($tambahanStokReal < 0) {
            throw new Exception('Kuantitas penerimaan baru ' . $item['nama_sparepart'] . ' tidak boleh lebih kecil dari jumlah penerimaan sebelumnya (' . $qtyTerimaLama . ' Pcs).');
        }

        // 1. Update kolom quantity_diterima di surat_jalan_detail menggunakan angka total akumulasi terbaru
        $updateDetail = mysqli_prepare($conn, "
            UPDATE surat_jalan_detail 
            SET quantity_diterima = ? 
            WHERE id = ? AND surat_jalan_id = ?
        ");
        mysqli_stmt_bind_param($updateDetail, 'iii', $qtyTerimaBaru, $detailId, $suratId);
        
        if (!mysqli_stmt_execute($updateDetail)) {
            throw new Exception('Gagal memperbarui kuantiti detail untuk ' . $item['nama_sparepart']);
        }

        // Acuan pencocokan status dokumen utama
        if ($qtyTerimaBaru === $qtyKirim) {
            $matchingItems++;
        }

        // HANYA jika ada penambahan barang susulan baru (> 0), proses stok dijalankan
        if ($tambahanStokReal > 0) {
            // 2. Catat riwayat log masuk HANYA sebesar selisih tambahannya saja
            $insertMasuk = mysqli_prepare($conn, "
                INSERT INTO sparepart_masuk_wk (user_id, sparepart_id, quantity)
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($insertMasuk, 'iii', $userId, $item['sparepart_id'], $tambahanStokReal);

            if (!mysqli_stmt_execute($insertMasuk)) {
                throw new Exception('Gagal mencatat riwayat sparepart masuk untuk ' . $item['nama_sparepart']);
            }

            // 3. Cek row master stok workshop
            $checkStok = mysqli_prepare($conn, "
                SELECT id FROM stok_sparepart_wk
                WHERE user_id = ? AND sparepart_id = ? FOR UPDATE
            ");
            mysqli_stmt_bind_param($checkStok, 'ii', $userId, $item['sparepart_id']);
            mysqli_stmt_execute($checkStok);
            $stokData = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStok));

            if ($stokData) {
                // Update master stok workshop dengan menambahkan nilai selisih barang susulannya saja
                $updateStok = mysqli_prepare($conn, "
                    UPDATE stok_sparepart_wk
                    SET stok = stok + ?
                    WHERE user_id = ? AND sparepart_id = ?
                ");
                mysqli_stmt_bind_param($updateStok, 'iii', $tambahanStokReal, $userId, $item['sparepart_id']);

                if (!mysqli_stmt_execute($updateStok)) {
                    throw new Exception('Gagal memperbarui stok untuk ' . $item['nama_sparepart']);
                }
            } else {
                // Jika data stok sparepart belum pernah ada sama sekali di workshop bersangkutan
                $insertStok = mysqli_prepare($conn, "
                    INSERT INTO stok_sparepart_wk (user_id, sparepart_id, stok)
                    VALUES (?, ?, ?)
                ");
                mysqli_stmt_bind_param($insertStok, 'iii', $userId, $item['sparepart_id'], $tambahanStokReal);

                if (!mysqli_stmt_execute($insertStok)) {
                    throw new Exception('Gagal membuat data stok baru untuk ' . $item['nama_sparepart']);
                }
            }
        }
    }

    // 4. Tentukan status berdasarkan pembaruan data real-time
    $statusBaru = ($matchingItems === $totalItems) ? 'diterima' : 'diterima_sebagian';

    // 5. Update status surat + perbarui tanggal_terima ke tanggal konfirmasi terbaru
    $updateSurat = mysqli_prepare($conn, "
        UPDATE surat_jalan
        SET status = ?, tanggal_terima = CURDATE()
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($updateSurat, 'si', $statusBaru, $suratId);

    if (!mysqli_stmt_execute($updateSurat)) {
        throw new Exception('Gagal memperbarui status surat jalan.');
    }

    mysqli_commit($conn);

    $statusText = ($statusBaru === 'diterima') ? 'Diterima Penuh' : 'Diterima Sebagian';
    $_SESSION['form_success'] = "Surat jalan \"{$surat['nomor_surat']}\" berhasil dikonfirmasi dengan status [{$statusText}]. Stok susulan berhasil ditambahkan.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['form_error'] = $e->getMessage();
}

header('Location: ../surat-pengiriman.php');
exit;