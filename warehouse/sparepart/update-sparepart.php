<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

// Validasi jika akses langsung bukan melalui POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sparepart.php');
    exit;
}

$id = trim($_POST['id'] ?? '');
$komponenId = trim($_POST['komponen_id'] ?? '');
$namaSparepart = trim($_POST['nama_sparepart'] ?? '');
$numberParts = $_POST['number_part'] ?? [];
$typeUnits = $_POST['type_unit'] ?? [];

// Validasi inputan kosong
if (empty($id) || empty($komponenId) || empty($namaSparepart)) {
    $_SESSION['form_error'] = 'Seluruh data wajib diisi!';
    header("Location: edit-sparepart.php?id=$id");
    exit;
}

// Bersihkan data array dari baris kosong atau spasi
$numberPartsClean = array_values(array_filter(array_map('trim', $numberParts)));
$typeUnitsClean = array_values(array_filter(array_map('trim', $typeUnits)));

// Number Part dan Type Unit sama-sama opsional (boleh kosong)
$numberPartJson = json_encode($numberPartsClean);
$typeUnitJson = json_encode($typeUnitsClean);

// ==================================================================
// == LOGIKA AMBIL DATA LAMA & CEK APAKAH NAMA SPAREPART BERUBAH ====
// ==================================================================
$checkQuery = "SELECT nama_sparepart, kode_sparepart FROM sparepart WHERE id = ?";
$checkStmt = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 'i', $id);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$currentData = mysqli_fetch_assoc($checkResult);

if (!$currentData) {
    $_SESSION['form_error'] = 'Data sparepart tidak ditemukan!';
    header('Location: ../sparepart.php');
    exit;
}

$kodeSparepart = $currentData['kode_sparepart'];

// Jika nama sparepart diubah, generate ulang kodenya berdasarkan nama baru
if ($currentData['nama_sparepart'] !== $namaSparepart) {
    
    // 1. Ambil 3 huruf pertama nama baru
    $cleanSparepartName = str_replace(' ', '', $namaSparepart);
    $prefixKode = strtoupper(substr($cleanSparepartName, 0, 3));

    if (strlen($prefixKode) < 3) {
        $prefixKode = str_pad($prefixKode, 3, 'X');
    }

    // 2. Hitung jumlah baris di DB dengan prefix yang sama untuk menentukan nomor urut baru
    $likePattern = $prefixKode . '-%';
    $countQuery = "SELECT COUNT(*) as total FROM sparepart WHERE kode_sparepart LIKE ? AND id != ?";
    $countStmt = mysqli_prepare($conn, $countQuery);
    mysqli_stmt_bind_param($countStmt, 'si', $likePattern, $id);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countData = mysqli_fetch_assoc($countResult);
    $nextNumber = $countData['total'] + 1;

    $suffixKode = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    
    // 3. Set kode sparepart baru
    $kodeSparepart = $prefixKode . '-' . $suffixKode;
}
// ==================================================================


// Query Update data sparepart ke database
$sql = "UPDATE sparepart SET komponen_id = ?, kode_sparepart = ?, nama_sparepart = ?, number_part = ?, type_unit = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'issssi', $komponenId, $kodeSparepart, $namaSparepart, $numberPartJson, $typeUnitJson, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['form_success'] = 'Data sparepart berhasil diperbarui! Kode saat ini: ' . $kodeSparepart;
        header('Location: ../sparepart.php');
        exit;
    }
}

$_SESSION['form_error'] = 'Gagal memperbarui data ke database.';
header("Location: edit-sparepart.php?id=$id");
exit;