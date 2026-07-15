<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nama = trim($_POST['nama'] ?? '');

// Simpan input lama untuk dikembalikan ke form kalau ada error
$_SESSION['old_input'] = [
    'nama' => $nama,
];

// Validasi field wajib
if (empty($nama)) {
    $_SESSION['form_error'] = 'Nama komponen wajib diisi.';
    header('Location: create-komponen.php');
    exit;
}

// Generate kode komponen: 3 huruf pertama dari nama, di-uppercase
$kode = strtoupper(substr($nama, 0, 3));

// Cek apakah kode sudah dipakai (karena harus UNIQUE di database)
$check = mysqli_prepare($conn, "SELECT id FROM komponen WHERE kode_komponen = ?");
mysqli_stmt_bind_param($check, 's', $kode);
mysqli_stmt_execute($check);
$existing = mysqli_stmt_get_result($check);

if (mysqli_fetch_assoc($existing)) {
    $_SESSION['form_error'] = "Kode komponen \"$kode\" sudah digunakan oleh komponen lain. Gunakan nama komponen yang berbeda.";
    header('Location: create-komponen.php');
    exit;
}

// Simpan ke database
$stmt = mysqli_prepare($conn, "INSERT INTO komponen (nama_komponen, kode_komponen) VALUES (?, ?)");
mysqli_stmt_bind_param($stmt, 'ss', $nama, $kode);

if (mysqli_stmt_execute($stmt)) {
    unset($_SESSION['old_input']);
    $_SESSION['form_success'] = "Komponen \"$nama\" berhasil ditambahkan dengan kode \"$kode\".";
    header('Location: ../komponen.php');
} else {
    $_SESSION['form_error'] = 'Gagal menyimpan data: ' . mysqli_error($conn);
    header('Location: create-komponen.php');
}
exit;