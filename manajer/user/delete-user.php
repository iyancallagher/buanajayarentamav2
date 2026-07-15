<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (empty($id)) {
    $_SESSION['form_error'] = 'ID user tidak valid.';
    header('Location: index.php');
    exit;
}

// Jangan biarkan user menghapus akunnya sendiri
if ($id == $_SESSION['user_id']) {
    $_SESSION['form_error'] = 'Tidak bisa menghapus akun yang sedang digunakan.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = 'User berhasil dihapus.';
} else {
    $_SESSION['form_error'] = 'Gagal menghapus user: ' . mysqli_error($conn);
}

header('Location: ../user.php');
exit;