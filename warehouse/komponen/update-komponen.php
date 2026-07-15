<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit-komponen.php?id=' . $_GET['id']);
    exit;
}

$id   = $_POST['id'] ?? null;
$nama = trim($_POST['nama'] ?? '');

$_SESSION['old_input'] = [
    'nama' => $nama,
];

if (empty($id) || empty($nama)) {
    $_SESSION['form_error'] = 'Nama komponen wajib diisi.';
    header('Location: edit-komponen.php?id=' . $id);
    exit;
}

// Ambil data komponen lama untuk dibandingkan (cek apakah nama benar-benar berubah)
$old = mysqli_prepare($conn, "SELECT nama_komponen, kode_komponen FROM komponen WHERE id = ?");
mysqli_stmt_bind_param($old, 'i', $id);
mysqli_stmt_execute($old);
$oldResult = mysqli_stmt_get_result($old);
$oldData = mysqli_fetch_assoc($oldResult);

if (!$oldData) {
    $_SESSION['form_error'] = 'Komponen tidak ditemukan.';
    header('Location: edit-komponen.php?id=' . $id);
    exit;
}

// Generate ulang kode HANYA kalau nama berubah
if ($nama !== $oldData['nama_komponen']) {

    $kodeBaru = strtoupper(substr($nama, 0, 3));

    // Cek apakah kode baru sudah dipakai komponen LAIN (selain komponen ini sendiri)
    $check = mysqli_prepare($conn, "SELECT id FROM komponen WHERE kode_komponen = ? AND id != ?");
    mysqli_stmt_bind_param($check, 'si', $kodeBaru, $id);
    mysqli_stmt_execute($check);
    $existing = mysqli_stmt_get_result($check);

    if (mysqli_fetch_assoc($existing)) {
        $_SESSION['form_error'] = "Kode komponen \"$kodeBaru\" sudah digunakan oleh komponen lain. Gunakan nama yang berbeda.";
        header('Location: edit-komponen.php?id=' . $id);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE komponen SET nama_komponen = ?, kode_komponen = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $nama, $kodeBaru, $id);

} else {
    // Nama tidak berubah, kode tetap, tidak perlu update kode
    $stmt = mysqli_prepare($conn, "UPDATE komponen SET nama_komponen = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $nama, $id);
}

if (mysqli_stmt_execute($stmt)) {
    unset($_SESSION['old_input']);
    $_SESSION['form_success'] = 'Komponen berhasil diperbarui.';
    header('Location: ../komponen.php');
} else {
    $_SESSION['form_error'] = 'Gagal memperbarui data: ' . mysqli_error($conn);
    header('Location: edit-komponen.php?id=' . $id);
}
exit;