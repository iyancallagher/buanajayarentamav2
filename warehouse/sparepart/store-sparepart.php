<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create-sparepart.php');
    exit;
}

$komponenId = trim($_POST['komponen_id'] ?? '');
$namaSparepart = trim($_POST['nama_sparepart'] ?? '');
$numberParts = $_POST['number_part'] ?? [];
$typeUnits = $_POST['type_unit'] ?? [];

$_SESSION['old_input'] = $_POST;

if (empty($komponenId) || empty($namaSparepart)) {
    $_SESSION['form_error'] = 'Komponen Induk dan Nama Sparepart wajib diisi!';
    header('Location: create-sparepart.php');
    exit;
}

// Bersihkan data array dari spasi kosong
$numberPartsClean = array_values(array_filter(array_map('trim', $numberParts)));
$typeUnitsClean = array_values(array_filter(array_map('trim', $typeUnits)));

// Number Part dan Type Unit sama-sama opsional (boleh kosong)
$numberPartJson = json_encode($numberPartsClean);
$typeUnitJson = json_encode($typeUnitsClean);

// === Pembuatan Kode Otomatis Berdasarkan 3 Huruf Nama Sparepart ===

// 1. Bersihkan spasi dari nama sparepart, ambil 3 huruf pertama, lalu jadikan HURUF KAPITAL
// Contoh: "Oil Filter" -> "OIL", "Gasket Valve" -> "GAS"
$cleanSparepartName = str_replace(' ', '', $namaSparepart);
$prefixKode = strtoupper(substr($cleanSparepartName, 0, 3));

// Jaga-jaga jika nama sparepart sangat pendek (kurang dari 3 huruf, misal "O")
if (strlen($prefixKode) < 3) {
    $prefixKode = str_pad($prefixKode, 3, 'X');
}

// 2. Hitung jumlah keseluruhan sparepart yang punya prefix sama untuk menentukan nomor urut selanjutnya
// Menggunakan LIKE 'ENG-%' agar urutannya berkesinambungan
$likePattern = $prefixKode . '-%';
$countQuery = "SELECT COUNT(*) as total FROM sparepart WHERE kode_sparepart LIKE ?";
$countStmt = mysqli_prepare($conn, $countQuery);
mysqli_stmt_bind_param($countStmt, 's', $likePattern);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$countData = mysqli_fetch_assoc($countResult);
$nextNumber = $countData['total'] + 1;

// 3. Format angka menjadi 3 digit (contoh: 001, 002)
$suffixKode = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

// 4. Gabungkan prefix dan nomor urut (Hasil: OIL-001, GAS-001)
$kodeSparepart = $prefixKode . '-' . $suffixKode;

// ==================================================================

// ==================================================================

// Simpan ke Database
$sql = "INSERT INTO sparepart (komponen_id, kode_sparepart, nama_sparepart, number_part, type_unit, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'issss', $komponenId, $kodeSparepart, $namaSparepart, $numberPartJson, $typeUnitJson);

    if (mysqli_stmt_execute($stmt)) {
        unset($_SESSION['old_input']);
        $_SESSION['form_success'] = 'Sparepart baru berhasil ditambahkan dengan kode: ' . $kodeSparepart;
        header('Location: ../sparepart.php');
        exit;
    }
}

$_SESSION['form_error'] = 'Gagal menyimpan data ke database.';
header('Location: create-sparepart.php');
exit;