<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

// ===== Auth check versi JSON (bukan redirect) =====
// Endpoint ini dipanggil dari service worker / fetch background,
// jadi kalau gagal auth, harus balas JSON, bukan header Location.
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'kepala workshop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Role tidak diizinkan.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

// ===== Ambil payload JSON =====
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload tidak valid.']);
    exit;
}

$userId   = $_SESSION['user_id'];
$uuid     = trim($data['uuid'] ?? '');
$typeUnit = trim($data['type_unit'] ?? '');
$nopol    = trim($data['nopol'] ?? '');
$mekanik  = trim($data['mekanik'] ?? '');
$rawItems = $data['items'] ?? [];

if (empty($uuid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'UUID wajib disertakan.']);
    exit;
}

if (empty($typeUnit) || empty($nopol) || empty($mekanik)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tipe unit, nomor polisi, dan nama mekanik wajib diisi.']);
    exit;
}

// ===== Cek duplikat lebih dulu (idempotency) =====
// Kalau uuid ini sudah pernah masuk (misal request sempat sukses tapi
// response-nya hilang di jalan lalu di-retry), langsung anggap sukses
// tanpa insert ulang / kurangi stok dua kali.
$checkUuid = mysqli_prepare($conn, "SELECT id FROM maintenance_wk WHERE uuid = ?");
mysqli_stmt_bind_param($checkUuid, 's', $uuid);
mysqli_stmt_execute($checkUuid);
$existing = mysqli_stmt_get_result($checkUuid)->fetch_assoc();

if ($existing) {
    echo json_encode(['success' => true, 'message' => 'Data sudah pernah tersinkron sebelumnya.', 'duplicate' => true]);
    exit;
}

// ===== Susun & validasi items (logic sama seperti store-maintenance.php) =====
$items = [];
foreach ($rawItems as $row) {
    $sparepartId = $row['sparepart_id'] ?? null;
    $quantity    = $row['quantity'] ?? null;

    if (empty($sparepartId) || empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
        continue;
    }
    $items[] = ['sparepart_id' => (int) $sparepartId, 'quantity' => (int) $quantity];
}

if (empty($items)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Minimal satu sparepart harus dipilih.']);
    exit;
}

$idsOnly = array_column($items, 'sparepart_id');
if (count($idsOnly) !== count(array_unique($idsOnly))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tidak boleh memilih sparepart yang sama lebih dari sekali.']);
    exit;
}

// ===== Validasi stok terkini (bisa sudah beda dari saat input offline) =====
$stokKurang = [];
foreach ($items as $item) {
    $checkStok = mysqli_prepare($conn, "
        SELECT sw.stok, s.nama_sparepart
        FROM stok_sparepart_wk sw
        JOIN sparepart s ON s.id = sw.sparepart_id
        WHERE sw.user_id = ? AND sw.sparepart_id = ?
    ");
    mysqli_stmt_bind_param($checkStok, 'ii', $userId, $item['sparepart_id']);
    mysqli_stmt_execute($checkStok);
    $stokData = mysqli_stmt_get_result($checkStok)->fetch_assoc();

    if (!$stokData) {
        $stokKurang[] = "Sparepart ID {$item['sparepart_id']} tidak ditemukan di stok kamu";
        continue;
    }
    if ($item['quantity'] > $stokData['stok']) {
        $stokKurang[] = $stokData['nama_sparepart'] . " (tersedia: {$stokData['stok']}, dibutuhkan: {$item['quantity']})";
    }
}

if (!empty($stokKurang)) {
    // PENTING: item ini TIDAK dihapus dari antrian client (lihat sync-queue.js) —
    // supaya workshop bisa lihat & revisi manual, bukan hilang begitu saja.
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Stok tidak cukup untuk: ' . implode(', ', $stokKurang) . '.',
        'stock_conflict' => true,
    ]);
    exit;
}

// ===== Semua valid, proses dalam SATU transaction =====
mysqli_begin_transaction($conn);

try {
    $sparepartListJson = json_encode($items);

    $insertMaintenance = mysqli_prepare($conn, "
        INSERT INTO maintenance_wk (uuid, type_unit, nopol, sparepart_list, mekanik, user_id, synced_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($insertMaintenance, 'sssssi', $uuid, $typeUnit, $nopol, $sparepartListJson, $mekanik, $userId);

    if (!mysqli_stmt_execute($insertMaintenance)) {
        throw new Exception('Gagal menyimpan data maintenance: ' . mysqli_error($conn));
    }

    foreach ($items as $item) {
        $updateStok = mysqli_prepare($conn, "
            UPDATE stok_sparepart_wk
            SET stok = stok - ?
            WHERE user_id = ? AND sparepart_id = ?
        ");
        mysqli_stmt_bind_param($updateStok, 'iii', $item['quantity'], $userId, $item['sparepart_id']);

        if (!mysqli_stmt_execute($updateStok)) {
            throw new Exception('Gagal mengurangi stok untuk sparepart ID ' . $item['sparepart_id'] . ': ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Data maintenance berhasil disinkronkan.']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}