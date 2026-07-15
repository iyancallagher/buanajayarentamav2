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
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Hitung total data =====
$countSql = "
    SELECT COUNT(*) as total
    FROM sparepart_masuk_wr m
    JOIN sparepart s ON s.id = m.sparepart_id
    WHERE 1=1
";
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

// ===== Query data utama dengan LEFT JOIN ke komponen =====
$sql = "
    SELECT m.id as masuk_id, m.quantity, m.created_at,
           s.kode_sparepart as kode,
           s.nama_sparepart as nama,
           s.number_part,
           s.type_unit,
           k.kode_komponen
";

if (!empty($searchValue)) {
    $sql .= ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
}

$sql .= "
    FROM sparepart_masuk_wr m
    JOIN sparepart s ON s.id = m.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($searchValue)) {
    $params[] = $booleanQuery;
    $types .= 's';

    $sql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";

    $params[] = $booleanQuery;
    $types .= 's';

    $sql .= " ORDER BY relevance_score DESC, m.created_at DESC";
} else {
    $sql .= " ORDER BY m.created_at DESC";
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

    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = htmlspecialchars($data['nama']);

    if (!empty($typeUnitText)) {
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    }

    if (!empty($numberPartText)) {
        $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);
    }

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    $tanggal = date('d M Y', strtotime($data['created_at']));

    // Tombol Aksi mengarah ke file edit baru di dalam folder sparepart-masuk
    $aksiHtml = '
        <div class="flex items-center justify-center">
            <a href="sparepart-masuk/edit-sparepart-masuk.php?id=' . $data['masuk_id'] . '"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50 transition-colors"
               title="Edit Jumlah Masuk">
                <i class="ti ti-pencil text-base"></i>
            </a>
        </div>
    ';

    $rows[] = [
        'no' => $no++,
        'tanggal' => $tanggal,
        'kode_sparepart' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama_sparepart' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'quantity' => '<div class="text-center font-semibold text-green-600">+' . number_format($data['quantity'], 0, ',', '.') . '</div>',
        'aksi' => $aksiHtml,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Tanggal', 'key' => 'tanggal'],
    ['label' => 'Kode Sparepart', 'key' => 'kode_sparepart', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama_sparepart', 'raw' => true],
    ['label' => 'Jumlah Masuk', 'key' => 'quantity', 'raw' => true, 'align' => 'center'],
    ['label' => 'Aksi', 'key' => 'aksi', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Riwayat Sparepart Masuk';
$emptyMessage = 'Belum ada data sparepart masuk.';
$tableActions = '<a href="' . BASE_URL . '/warehouse/sparepart-masuk/create-sparepart-masuk.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap"><i class="ti ti-plus text-base"></i> Tambah Sparepart Masuk</a>';

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
        <h1 class="text-2xl font-bold text-slate-800">Sparepart Masuk</h1>
        <p class="text-slate-500 mt-1">Riwayat penerimaan sparepart ke warehouse.</p>
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