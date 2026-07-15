<?php

require_once '../auth/auth_check.php';
requireRole(['manajer operasional']);

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

// ===== Helper bangun WHERE =====
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

[$where, $countParams, $countTypes] = buildWhere($booleanQuery, $filterBulan);

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total
    FROM sparepart_masuk_wr m
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

// Tambahkan parameter skor kecocokan teks jika user melakukan pencarian
$relevanceSelect = "";
$orderByClause = "ORDER BY m.created_at DESC";

if (!empty($booleanQuery)) {
    $relevanceSelect = ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
    $orderByClause = "ORDER BY relevance_score DESC, m.created_at DESC";

    // Tempatkan parameter booleanQuery di urutan pertama array karena dipanggil pada bagian SELECT
    array_unshift($params, $booleanQuery);
    $types = 's' . $types;
}

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conn, "
    SELECT m.id, m.quantity, m.created_at,
           s.kode_sparepart AS kode,
           s.nama_sparepart AS nama,
           s.number_part,
           s.type_unit,
           k.kode_komponen
           $relevanceSelect
    FROM sparepart_masuk_wr m
    JOIN sparepart s ON s.id = m.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    $where
    $orderByClause
    LIMIT ? OFFSET ?
");
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
    if (!empty($typeUnitText))
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    if (!empty($numberPartText))
        $namaLengkap .= ' ' . htmlspecialchars($numberPartText);

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    $rows[] = [
        'no' => $no++,
        'tanggal' => date('d M Y, H:i', strtotime($data['created_at'])),
        'kode_sparepart' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama_sparepart' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'quantity' => '<div class="text-center font-semibold text-green-600">+' . number_format((int) $data['quantity'], 0, ',', '.') . '</div>',
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Tanggal', 'key' => 'tanggal'],
    ['label' => 'Kode Sparepart', 'key' => 'kode_sparepart', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama_sparepart', 'raw' => true],
    ['label' => 'Jumlah Masuk', 'key' => 'quantity', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Riwayat Sparepart Masuk';
$emptyMessage = 'Belum ada data sparepart masuk.';
$tableActions = '';
$showSearch = false;

$baseQuery = array_filter([
    'search' => $searchValue,
    'bulan' => $filterBulan,
]);

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Manajer Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Sparepart Masuk</h1>
        <p class="text-slate-500 mt-1">Riwayat penerimaan sparepart ke warehouse.</p>
    </div>

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
                    class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-all duration-200 whitespace-nowrap">
                    <i class="ti ti-x mr-1"></i> Reset
                </a>
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
                    class="ml-1 text-blue-400 hover:text-blue-600">
                    <i class="ti ti-x text-xs"></i>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>

</main>

<?php include '../layouts/footer.php'; ?>