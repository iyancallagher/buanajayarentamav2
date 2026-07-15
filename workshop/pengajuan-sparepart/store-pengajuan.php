<?php
session_start();
require_once '../../auth/auth_check.php';
requireRole(['kepala workshop']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pengajuan-sparepart.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$userSite = $_SESSION['site'] ?? 'dalam kota';

$sparepartId      = $_POST['sparepart_id'] ?? null;
$kondisiSparepart = trim($_POST['kondisi_sparepart'] ?? '');
$quantity         = 0; 

// ===== Validasi field wajib =====
if (empty($sparepartId) || empty($kondisiSparepart)) {
    $_SESSION['form_error'] = 'Sparepart dan kondisi fisik wajib diisi.';
    header('Location: create-sparepart.php');
    exit;
}

// Pastikan sparepart_id valid
$checkSparepart = mysqli_prepare($conn, "SELECT id FROM sparepart WHERE id = ?");
mysqli_stmt_bind_param($checkSparepart, 'i', $sparepartId);
mysqli_stmt_execute($checkSparepart);
$sparepartExists = mysqli_stmt_get_result($checkSparepart);

if (!mysqli_fetch_assoc($sparepartExists)) {
    $_SESSION['form_error'] = 'Sparepart tidak ditemukan.';
    header('Location: create-sparepart.php');
    exit;
}

// ===== Validasi foto sesuai site =====
$wajibFoto = ($userSite === 'luar kota');
$fotoFiles = $_FILES['foto'] ?? null;

$jumlahFotoTerupload = 0;
if (!empty($fotoFiles) && !empty($fotoFiles['name'][0])) {
    $jumlahFotoTerupload = count(array_filter($fotoFiles['name']));
}

if ($wajibFoto && $jumlahFotoTerupload === 0) {
    $_SESSION['form_error'] = 'Foto wajib diisi';
    header('Location: create-pengajuan.php');
    exit;
}

if ($jumlahFotoTerupload > 3) {
    $_SESSION['form_error'] = 'Maksimal 3 foto.';
    header('Location: create-pengajuan.php');
    exit;
}

// ===== Proses upload foto =====
$fotoPaths = [];
if ($jumlahFotoTerupload > 0) {
    $uploadDir = '../../uploads/pengajuan/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

    for ($i = 0; $i < count($fotoFiles['name']); $i++) {
        if (empty($fotoFiles['name'][$i])) {
            continue;
        }

        $tmpName  = $fotoFiles['tmp_name'][$i];
        if ($_FILES['foto']['error'][$i] !== UPLOAD_ERR_OK) {
            $_SESSION['form_error'] = 'Terjadi kesalahan sistem saat mengunggah gambar.';
            header('Location: create-pengajuan.php');
            exit;
        }

        $mimeType = mime_content_type($tmpName);
        if (!in_array($mimeType, $allowedTypes)) {
            $_SESSION['form_error'] = 'Format foto tidak didukung. Gunakan JPG, PNG, atau WebP.';
            header('Location: create-pengajuan.php');
            exit;
        }

        $extension = pathinfo($fotoFiles['name'][$i], PATHINFO_EXTENSION);
        $fileName  = 'pengajuan_' . $userId . '_' . time() . '_' . $i . '.' . $extension;
        $destPath  = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $destPath)) {
            $fotoPaths[] = '/uploads/pengajuan/' . $fileName;
        } else {
            $_SESSION['form_error'] = 'Gagal mengunggah salah satu foto.';
            header('Location: create-pengajuan.php');
            exit;
        }
    }
}

$fotoJson = !empty($fotoPaths) ? json_encode($fotoPaths) : null;

// ===== Insert ke database =====
$stmt = mysqli_prepare($conn, "
    INSERT INTO pengajuan_sparepart (user_id, sparepart_id, foto_sparepart, kondisi_sparepart, quantity, status)
    VALUES (?, ?, ?, ?, ?, 'draft')
");
mysqli_stmt_bind_param($stmt, 'iissi', $userId, $sparepartId, $fotoJson, $kondisiSparepart, $quantity);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['form_success'] = 'Pengajuan sparepart berhasil dikirim, menunggu penentuan quantity oleh manajer.';
    header('Location: ../pengajuan-sparepart.php');
} else {
    $_SESSION['form_error'] = 'Gagal menyimpan pengajuan: ' . mysqli_error($conn);
    header('Location: create-pengajuan.php');
}
exit;