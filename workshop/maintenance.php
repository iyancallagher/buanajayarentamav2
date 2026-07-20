<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$userId = $_SESSION['user_id'];
$searchValue = $_GET['search'] ?? '';

// ===== Setup pagination =====
$perPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ===== Hitung total data =====
$countSql = "SELECT COUNT(*) as total FROM maintenance_wk WHERE user_id = ?";
$countParams = [$userId];
$countTypes = 'i';

if (!empty($searchValue)) {
    $countSql .= " AND (type_unit LIKE ? OR nopol LIKE ? OR mekanik LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countTypes .= 'sss';
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama =====
$sql = "
    SELECT id, type_unit, nopol, sparepart_list, mekanik, created_at
    FROM maintenance_wk
    WHERE user_id = ?
";

$params = [$userId];
$types = 'i';

if (!empty($searchValue)) {
    $sql .= " AND (type_unit LIKE ? OR nopol LIKE ? OR mekanik LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $params[] = $likeValue;
    $params[] = $likeValue;
    $params[] = $likeValue;
    $types .= 'sss';
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Kumpulkan semua sparepart_id unik dari semua maintenance, supaya bisa diambil sekali query (hindari N+1)
$maintenanceRaw = [];
$allSparepartIds = [];

while ($data = mysqli_fetch_assoc($result)) {
    $sparepartItems = json_decode($data['sparepart_list'], true) ?: [];
    foreach ($sparepartItems as $item) {
        $allSparepartIds[] = $item['sparepart_id'];
    }
    $data['sparepart_items'] = $sparepartItems;
    $maintenanceRaw[] = $data;
}

$allSparepartIds = array_unique($allSparepartIds);
$sparepartDetailMap = [];

if (!empty($allSparepartIds)) {
    $placeholders = implode(',', array_fill(0, count($allSparepartIds), '?'));
    $types2 = str_repeat('i', count($allSparepartIds));

    $detailSql = "
        SELECT s.id, s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit, k.kode_komponen
        FROM sparepart s
        LEFT JOIN komponen k ON k.id = s.komponen_id
        WHERE s.id IN ($placeholders)
    ";

    $detailStmt = mysqli_prepare($conn, $detailSql);
    mysqli_stmt_bind_param($detailStmt, $types2, ...$allSparepartIds);
    mysqli_stmt_execute($detailStmt);
    $detailResult = mysqli_stmt_get_result($detailStmt);

    while ($d = mysqli_fetch_assoc($detailResult)) {

        $typeUnitArray = json_decode($d['type_unit'] ?? '[]', true) ?: [];
        $numberPartArray = json_decode($d['number_part'] ?? '[]', true) ?: [];

        $typeUnitText = implode(' / ', $typeUnitArray);
        $numberPartText = implode('/', $numberPartArray);

        $namaLengkap = $d['nama_sparepart'];
        if (!empty($typeUnitText))
            $namaLengkap .= ' / ' . $typeUnitText;
        if (!empty($numberPartText))
            $namaLengkap .= '/ ' . $numberPartText;

        $kodeGabungan = !empty($d['kode_komponen'])
            ? $d['kode_komponen'] . '-' . $d['kode_sparepart']
            : $d['kode_sparepart'];

        $sparepartDetailMap[$d['id']] = [
            'nama' => $namaLengkap,
            'kode' => $kodeGabungan,
        ];
    }
}

// ===== Susun rows untuk komponen table =====
$rows = [];
$no = $offset + 1;
foreach ($maintenanceRaw as $data) {

    $tanggal = date('d M Y', strtotime($data['created_at']));

    $sparepartHtml = '<div class="space-y-1">';
    foreach ($data['sparepart_items'] as $item) {
        $detail = $sparepartDetailMap[$item['sparepart_id']] ?? null;
        if ($detail) {
            $sparepartHtml .= '<div class="text-sm text-slate-700">' . htmlspecialchars($detail['nama']) . ' <span class="font-semibold">x' . (int) $item['quantity'] . '</span></div>';
        }
    }
    $sparepartHtml .= '</div>';

    $rows[] = [
        'no' => $no++,
        'tanggal' => $tanggal,
        'unit' => '<div class="font-medium text-slate-800">' . htmlspecialchars($data['type_unit']) . '</div><div class="text-xs text-slate-400 font-mono">' . htmlspecialchars($data['nopol']) . '</div>',
        'sparepart' => $sparepartHtml,
        'mekanik' => '<span class="text-sm text-slate-600">' . htmlspecialchars($data['mekanik']) . '</span>',
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Tanggal', 'key' => 'tanggal'],
    ['label' => 'Unit', 'key' => 'unit', 'raw' => true],
    ['label' => 'Sparepart', 'key' => 'sparepart', 'raw' => true],
    ['label' => 'Mekanik', 'key' => 'mekanik', 'raw' => true],
];

$tableTitle = 'Riwayat Maintenance';
$emptyMessage = 'Belum ada data maintenance.';
$tableActions = '<a href="' . BASE_URL . '/workshop/maintenance/create-maintenance.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap"><i class="ti ti-plus text-base"></i> Catat Maintenance</a>';

$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Workshop Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Maintenance</h1>
        <p class="text-slate-500 mt-1">Riwayat penggunaan sparepart untuk maintenance unit.</p>
    </div>

    <?php if (!empty($_SESSION['form_success'])): ?>
        <div
            class="mb-6 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2">
            <i class="ti ti-circle-check text-base shrink-0"></i>
            <?= htmlspecialchars($_SESSION['form_success']) ?>
        </div>
        <?php unset($_SESSION['form_success']); endif; ?>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>

</main>

<!-- Load database lokal dan manajemen antrean sinkronisasi -->
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync-queue.js"></script>

<script>
// Pemicu otomatis: Setiap kali halaman maintenance dibuka atau kembali aktif dalam kondisi online,
// jalankan sinkronisasi untuk data offline yang tersisa.
document.addEventListener('DOMContentLoaded', () => {
    if (navigator.onLine) {
        if (typeof syncMaintenanceQueue === 'function') {
            console.log("Mendeteksi status online, memproses antrean data lokal...");
            syncMaintenanceQueue();
        }
    }
});
</script>

<?php include '../layouts/footer.php'; ?>