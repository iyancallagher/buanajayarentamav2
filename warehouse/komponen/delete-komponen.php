<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../komponen.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (empty($id)) {
    $_SESSION['form_error'] = 'ID komponen tidak valid.';
    header('Location: ../komponen.php');
    exit;
}

// Jangan biarkan user menghapus akunnya sendiri
if ($id == $_SESSION['user_id']) {
    $_SESSION['form_error'] = 'Tidak bisa menghapus akun yang sedang digunakan.';
    header('Location: ../komponen.php');
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM komponen WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = 'Komponen berhasil dihapus.';
} else {
    $_SESSION['form_error'] = 'Gagal menghapus komponen: ' . mysqli_error($conn);
}

header('Location: ../komponen.php');
exit;