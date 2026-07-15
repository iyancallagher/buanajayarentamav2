<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id       = $_POST['id'] ?? null;
$nama     = trim($_POST['nama'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';
$site     = $_POST['site'] ?? '';

if (empty($id) || empty($nama) || empty($email) || empty($role) || empty($site)) {
    $_SESSION['form_error'] = 'Semua field wajib diisi.';
    header('Location: edit-user.php?id=' . $id);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['form_error'] = 'Format email tidak valid.';
    header('Location: edit-user.php?id=' . $id);
    exit;
}

// Cek email tidak dipakai oleh user lain
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
mysqli_stmt_bind_param($check, 'si', $email, $id);
mysqli_stmt_execute($check);
$existing = mysqli_stmt_get_result($check);

if (mysqli_fetch_assoc($existing)) {
    $_SESSION['form_error'] = 'Email sudah dipakai oleh user lain.';
    header('Location: edit-user.php?id=' . $id);
    exit;
}

// Kalau password diisi, update sekalian. Kalau kosong, password lama tetap dipakai.
if (!empty($password)) {
    if (strlen($password) < 8) {
        $_SESSION['form_error'] = 'Password minimal 8 karakter.';
        header('Location: edit-user.php?id=' . $id);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, email = ?, password = ?, role = ?, site = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'sssssi', $nama, $email, $hashedPassword, $role, $site, $id);
} else {
    $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, email = ?, role = ?, site = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssssi', $nama, $email, $role, $site, $id);
}

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = 'Data user berhasil diperbarui.';
    header('Location: ../user.php');
} else {
    $_SESSION['form_error'] = 'Gagal memperbarui data: ' . mysqli_error($conn);
    header('Location: edit-user.php?id=' . $id);
}
exit;