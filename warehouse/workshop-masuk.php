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
$filterBulan = $_GET['bulan'] ?? '';

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Helper bangun WHERE Clause secara dinamis =====
function buildWhere(string $booleanQuery, string $bulan): array
{
    $where = "WHERE 1=1";
    $params = [];
    $types = '';

    if (!empty($booleanQuery)) {
        $where .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
        $params[] = $booleanQuery;
        $types .= 's';
    }

    if (!empty($bulan)) {
        $where .= " AND DATE_FORMAT(m.created_at, '%Y-%m') = ?";
        $params[] = $bulan;
        $types .= 's';
    }

    return [$where, $params, $types];
}

// ===== Pagination =====
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// Hitung total data untuk pagination
[$where, $countParams, $countTypes] = buildWhere($booleanQuery, $filterBulan);

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total
    FROM sparepart_masuk_wk m
    JOIN sparepart s ON s.id = m.sparepart_id
    $where
");
if (!empty($countParams)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}
mysqli_stmt_execute($countStmt);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];

// ===== Query utama =====
[$where, $params, $types] = buildWhere($booleanQuery, $filterBulan);

// Daftarkan parameter untuk relevansi score jika sedang mencari data
$relevanceSelect = "";
if (!empty($booleanQuery)) {
    $relevanceSelect = ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
    // Disisipkan di awal array parameter karena diletakkan di bagian SELECT
    array_unshift($params, $booleanQuery);
    $types = 's' . $types;
}

// Tambahkan parameter LIMIT & OFFSET di akhir array
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

// Atur pengurutan data: prioritaskan relevansi jika melakukan pencarian
$orderBy = "ORDER BY m.created_at DESC";
if (!empty($booleanQuery)) {
    $orderBy = "ORDER BY relevance_score DESC, m.created_at DESC";
}

$stmt = mysqli_prepare($conn, "
    SELECT m.id AS log_id, m.quantity, m.created_at,
           s.kode_sparepart AS kode,
           s.nama_sparepart AS nama,
           s.number_part,
           s.type_unit,
           k.kode_komponen,
           u.nama AS nama_workshop
           $relevanceSelect
    FROM sparepart_masuk_wk m
    JOIN sparepart s ON s.id = m.sparepart_id
    JOIN users u ON u.id = m.user_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    $where
    $orderBy
    LIMIT ? OFFSET ?
");

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
$no = $offset + 1;
while ($data = mysqli_fetch_assoc($result)) {
    $logId = (int) $data['log_id'];
    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];
    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = htmlspecialchars($data['nama']);
    if (!empty($typeUnitText))
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    if (!empty($numberPartText))
        $namaLengkap .= ' ' . htmlspecialchars($numberPartText);

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    $aksiHtml = '
        <div class="flex items-center justify-center gap-2">
            <a href="workshop-masuk/edit-workshop-masuk.php?id=' . $logId . '"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50 transition-colors"
               title="Koreksi Transaksi">
                <i class="ti ti-pencil text-base"></i>
            </a>
        </div>
    ';

    $rows[] = [
        'no' => $no++,
        'tanggal' => date('d M Y', strtotime($data['created_at'])),
        'kode_sparepart' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama_sparepart' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'workshop' => '<div class="text-sm text-slate-600 font-medium">' . htmlspecialchars($data['nama_workshop']) . '</div>',
        'quantity' => '<div class="text-center font-semibold text-green-600">+' . number_format((int) $data['quantity'], 0, ',', '.') . '</div>',
        'aksi' => $aksiHtml,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Tanggal Masuk', 'key' => 'tanggal'],
    ['label' => 'Kode Sparepart', 'key' => 'kode_sparepart', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama_sparepart', 'raw' => true],
    ['label' => 'Workshop Tujuan', 'key' => 'workshop', 'raw' => true],
    ['label' => 'Jumlah Masuk', 'key' => 'quantity', 'raw' => true, 'align' => 'center'],
    ['label' => 'Aksi', 'key' => 'aksi', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Riwayat Sparepart Masuk Workshop';
$emptyMessage = 'Belum ada data riwayat sparepart masuk ke workshop.';
$tableActions = '';
$showSearch = false;

$baseQuery = array_filter([
    'search' => $searchValue,
    'bulan' => $filterBulan,
]);

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
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">warehouse Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Sparepart Masuk Workshop</h1>
        <p class="text-slate-500 mt-1">Riwayat register penerimaan distribusi sparepart ke seluruh unit workshop.</p>
    </div>

    <?php if (!empty($formSuccess)): ?>
        <div
            class="mb-4 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2 shadow-sm">
            <i class="ti ti-circle-check text-base shrink-0 text-green-500"></i>
            <?= htmlspecialchars($formSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($formError)): ?>
        <div
            class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2 shadow-sm">
            <i class="ti ti-alert-circle text-base shrink-0 text-red-500"></i>
            <?= htmlspecialchars($formError) ?>
        </div>
    <?php endif; ?>

    <form method="GET" action=""
        class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">

        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                <i class="ti ti-search text-base"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($searchValue) ?>"
                placeholder="Cari nama, kode, number part..."
                class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition" />
        </div>

        <div class="relative sm:w-48">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                <i class="ti ti-calendar-month text-base"></i>
            </span>
            <input type="month" name="bulan" value="<?= htmlspecialchars($filterBulan) ?>"
                class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition cursor-pointer" />
        </div>

        <div class="flex gap-2 shrink-0">
            <button type="submit"
                class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-sm hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap">
                <i class="ti ti-search mr-1"></i> Cari
            </button>
            <?php if (!empty($searchValue) || !empty($filterBulan)): ?>
                <a href="?"
                    class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-all duration-200 whitespace-nowrap"><i
                        class="ti ti-x mr-1"></i> Reset</a>
            <?php endif; ?>
        </div>

    </form>

    <?php if (!empty($filterBulan)): ?>
        <div class="mb-4 flex items-center gap-2">
            <span class="text-xs text-slate-500">Filter aktif:</span>
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                <i class="ti ti-calendar-month text-xs"></i>
                <?= date('F Y', strtotime($filterBulan . '-01')) ?>
                <a href="<?= !empty($searchValue) ? '?search=' . urlencode($searchValue) : '?' ?>"
                    class="ml-1 text-blue-400 hover:text-blue-600"><i class="ti ti-x text-xs"></i></a>
            </span>
        </div>
    <?php endif; ?>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>

</main>

<?php include '../layouts/footer.php'; ?>