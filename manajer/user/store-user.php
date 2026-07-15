<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nama     = trim($_POST['nama'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';
$site     = $_POST['site'] ?? '';

// Simpan input lama untuk dikembalikan ke form kalau ada error (kecuali password)
$_SESSION['old_input'] = [
    'nama'  => $nama,
    'email' => $email,
    'role'  => $role,
];

// Validasi field wajib
if (empty($nama) || empty($email) || empty($password) || empty($role) || empty($site)) {
    $_SESSION['form_error'] = 'Semua field wajib diisi.';
    header('Location: create-user.php');
    exit;
}

// Validasi format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['form_error'] = 'Format email tidak valid.';
    header('Location: create-user.php');
    exit;
}

// Validasi panjang password
if (strlen($password) < 8) {
    $_SESSION['form_error'] = 'Password minimal 8 karakter.';
    header('Location: create-user.php');
    exit;
}

// Validasi role sesuai enum di database
$allowedRoles = ['manajer operasional', 'kepala gudang', 'kepala workshop'];
if (!in_array($role, $allowedRoles)) {
    $_SESSION['form_error'] = 'Role tidak valid.';
    header('Location: create-user.php');
    exit;
}

// Validasi site sesuai enum di database
$allowedSites = ['dalam kota', 'luar kota'];
if (!in_array($site, $allowedSites)) {
    $_SESSION['form_error'] = 'Site tidak valid.';
    header('Location: create-user.php');
    exit;
}

// Cek email sudah dipakai atau belum
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
$existing = mysqli_stmt_get_result($check);

if (mysqli_fetch_assoc($existing)) {
    $_SESSION['form_error'] = 'Email sudah terdaftar, gunakan email lain.';
    header('Location: create-user.php');
    exit;
}

// Hash password dan simpan
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "INSERT INTO users (nama, email, password, role, site) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'sssss', $nama, $email, $hashedPassword, $role, $site);

if (mysqli_stmt_execute($stmt)) {
    unset($_SESSION['old_input']);
    $_SESSION['form_success'] = 'User berhasil ditambahkan.';
    header('Location: ../user.php');
} else {
    $_SESSION['form_error'] = 'Gagal menyimpan data: ' . mysqli_error($conn);
    header('Location: create-user.php');
}
exit;