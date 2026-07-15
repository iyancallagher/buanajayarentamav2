<?php
session_start();
require_once '../config/database.php'; // sesuaikan path ke file koneksi $conn kamu

// Pastikan request via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input kosong
if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Email dan password wajib diisi.';
    header('Location: login.php');
    exit;
}

// Cari user berdasarkan email
$stmt = mysqli_prepare($conn, "SELECT id, nama, email, password, role, site FROM users WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Cek user ditemukan dan password cocok
if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'Email atau password salah.';
    header('Location: login.php');
    exit;
}

// Login berhasil — set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['nama']    = $user['nama'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$_SESSION['site']    = $user['site'];

// Remember me (opsional, simpan cookie email 30 hari)
if (!empty($_POST['remember'])) {
    setcookie('remember_email', $user['email'], time() + (30 * 24 * 60 * 60), '/');
}

// Redirect sesuai role
switch ($user['role']) {
    case 'manajer operasional':
        header('Location: ../manajer/index.php');
        break;

    case 'kepala gudang':
        header('Location: ../warehouse/index.php');
        break;

    case 'kepala workshop':
        header('Location: ../workshop/index.php');
        break;

    default:
        header('Location: login.php');
        break;
}
exit;