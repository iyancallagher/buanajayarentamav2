<?php

require_once '../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$searchValue = $_GET['search'] ?? '';

// ===== Setup pagination =====
$perPage = 100;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Hitung total data untuk pagination =====
$countSql = "SELECT COUNT(*) as total FROM sparepart s WHERE s.deleted_at IS NULL";
$countParams = [];
$countTypes = '';

if (!empty($searchValue)) {
    $countSql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $countParams[] = $booleanQuery;
    $countTypes .= 's';
}

$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($countParams)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama =====
$sql = "
    SELECT s.id,
           s.nama_sparepart as nama,
           s.kode_sparepart as kode,
           s.number_part,
           s.type_unit,
           w.updated_at,
           k.kode_komponen,
           IFNULL(w.id, 0) as stok_id,
           IFNULL(w.stok, 0) as stok,
           IFNULL(w.minimal_stok, 0) as minimal_stok
";

if (!empty($searchValue)) {
    $sql .= ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
}

$sql .= "
    FROM sparepart s
    LEFT JOIN komponen k ON k.id = s.komponen_id
    LEFT JOIN stok_sparepart_wr w ON s.id = w.sparepart_id
    WHERE s.deleted_at IS NULL
";

$params = [];
$types = '';

if (!empty($searchValue)) {
    $params[] = $booleanQuery;
    $types .= 's';

    $sql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $params[] = $booleanQuery;
    $types .= 's';

    $sql .= " ORDER BY relevance_score DESC, stok ASC";
} else {
    $sql .= " ORDER BY stok ASC, s.nama_sparepart ASC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
$no = $offset + 1;
while ($data = mysqli_fetch_assoc($result)) {

    $stok = (int) $data['stok'];
    $minimalStok = (int) $data['minimal_stok'];

    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = htmlspecialchars($data['nama']);
    if (!empty($typeUnitText))
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    if (!empty($numberPartText))
        $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    if ($stok <= 0) {
        $stokBadge = '<div class="text-center">' . renderBadge('Habis', 'red') . '</div>';
    } elseif ($stok <= $minimalStok) {
        $stokBadge = '<div class="text-center">' . renderBadge($stok . ' Pcs', 'yellow') . '</div>';
    } else {
        $stokBadge = '<div class="text-center">' . renderBadge($stok . ' Pcs', 'green') . '</div>';
    }

    // Format tanggal terakhir diedit (dari stok_sparepart_wr, bukan sparepart)
    $editedAt = !empty($data['updated_at'])
        ? date('d M Y', strtotime($data['updated_at']))
        : '-';

    $aksiHtml = '
        <div class="flex items-center justify-center gap-2">
            <a href="sparepart-stok/sparepart-edit.php?id=' . $data['id'] . '"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50 transition-colors"
               title="Edit Stok">
                <i class="ti ti-pencil text-base"></i>
            </a>
        </div>
    ';

    $rows[] = [
        'no' => $no++,
        'kode' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'minimal_stok' => '<div class="text-center text-slate-500 text-sm">' . $minimalStok . ' Pcs</div>',
        'stok' => $stokBadge,
        'edited_at' => '<div class="text-center text-slate-500 text-xs">' . $editedAt . '</div>',
        'aksi' => $aksiHtml,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Kode Sparepart', 'key' => 'kode', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama', 'raw' => true],
    ['label' => 'Batas Minimum', 'key' => 'minimal_stok', 'raw' => true, 'align' => 'center'],
    ['label' => 'Jumlah Stok', 'key' => 'stok', 'raw' => true, 'align' => 'center'],
    ['label' => 'Terakhir Diedit', 'key' => 'edited_at', 'raw' => true, 'align' => 'center'],
    ['label' => 'Aksi', 'key' => 'aksi', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Stok Warehouse';
$emptyMessage = 'Data stok sparepart tidak ditemukan.';
$tableActions = '';

$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}
if (!empty($_SESSION['form_success'])):
    $formSuccess = $_SESSION['form_success'];
    unset($_SESSION['form_success']);
endif;
if (!empty($_SESSION['form_error'])):
    $formError = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
endif;
?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Stok Sparepart</h1>
        <p class="text-slate-500 mt-1"> stok sparepart warehouse.</p>
    </div>

    <?php if (!empty($formSuccess)): ?>
        <div
            class="mb-6 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2">
            <i class="ti ti-circle-check text-base shrink-0"></i>
            <?= htmlspecialchars($formSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($formError)): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
            <i class="ti ti-alert-circle text-base shrink-0"></i>
            <?= htmlspecialchars($formError) ?>
        </div>
    <?php endif; ?>
    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>
</main>

<?php include '../layouts/footer.php'; ?>