<?php
session_start();

// Kalau belum login, redirect ke halaman login
if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Fungsi bantu untuk cek role tertentu (dipakai per halaman kalau perlu restrict role)
function requireRole(array $allowedRoles) {
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Role tidak sesuai, tendang ke dashboard masing-masing atau halaman 403
        header('Location: ../403.php');
        exit;
    }
}