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
$filterKomponen = (int) ($_GET['komponen'] ?? 0);

// ===== Ambil semua komponen untuk dropdown filter =====
$komponenList = [];
$resKomponen = mysqli_query($conn, "SELECT id, kode_komponen, nama_komponen FROM komponen ORDER BY nama_komponen ASC");
while ($k = mysqli_fetch_assoc($resKomponen)) {
    $komponenList[] = $k;
}

// ===== Susun _boolean query_ untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Helper: bangun klausa WHERE + params =====
function buildWhere(string $booleanQuery, int $filterKomponen): array
{
    $where = "WHERE s.deleted_at IS NULL";
    $params = [];
    $types = '';

    if ($filterKomponen > 0) {
        $where .= " AND s.komponen_id = ?";
        $params[] = $filterKomponen;
        $types .= 'i';
    }

    if (!empty($booleanQuery)) {
        $where .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
        $params[] = $booleanQuery;
        $types .= 's';
    }

    return [$where, $params, $types];
}

// ===== Pagination =====
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

[$where, $countParams, $countTypes] = buildWhere($booleanQuery, $filterKomponen);

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM sparepart s $where");
if (!empty($countParams)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}
mysqli_stmt_execute($countStmt);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];

// ===== Query utama =====
[$where, $params, $types] = buildWhere($booleanQuery, $filterKomponen);

// Masukkan parameter relevance score jika ada pencarian teks aktif
$relevanceSelect = "";
$orderByClause = "ORDER BY stok ASC, s.nama_sparepart ASC";

if (!empty($booleanQuery)) {
    $relevanceSelect = ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
    $orderByClause = "ORDER BY relevance_score DESC, stok ASC, s.nama_sparepart ASC";

    // Taruh parameter pencarian ke urutan pertama di dalam array karena dipanggil pada klausa SELECT
    array_unshift($params, $booleanQuery);
    $types = 's' . $types;
}

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$sql = "
    SELECT s.id,
           s.nama_sparepart AS nama,
           s.kode_sparepart AS kode,
           s.number_part,
           s.type_unit,
           k.kode_komponen,
           k.nama_komponen,
           IFNULL(w.stok, 0)         AS stok,
           IFNULL(w.minimal_stok, 0) AS minimal_stok
           $relevanceSelect
    FROM sparepart s
    LEFT JOIN komponen k ON k.id = s.komponen_id
    LEFT JOIN stok_sparepart_wr w ON s.id = w.sparepart_id
    $where
    $orderByClause
    LIMIT ? OFFSET ?
";

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
        $namaLengkap .= ' /' . htmlspecialchars($numberPartText);

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

    // format data array
    $rows[] = [
        'no' => $no++,
        'kode' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'komponen' => '<div class="text-center text-sm text-slate-600">'
            . htmlspecialchars($data['nama_komponen'] ?? '-')
            . '</div>',
        'minimal_stok' => '<div class="text-center text-slate-500 text-sm">' . $minimalStok . ' Pcs</div>',
        'stok' => $stokBadge,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Kode Sparepart', 'key' => 'kode', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama', 'raw' => true],
    ['label' => 'Komponen', 'key' => 'komponen', 'raw' => true],
    ['label' => 'Batas Minimum', 'key' => 'minimal_stok', 'raw' => true, 'align' => 'center'],
    ['label' => 'Jumlah Stok', 'key' => 'stok', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Monitoring Stok Warehouse';
$emptyMessage = 'Data stok sparepart tidak ditemukan.';
$tableActions = '';
$showSearch = false;

$baseQuery = [];
if (!empty($searchValue))
    $baseQuery['search'] = $searchValue;
if ($filterKomponen > 0)
    $baseQuery['komponen'] = $filterKomponen;

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Manajer Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Stok Warehouse</h1>
        <p class="text-slate-500 mt-1">Monitoring ketersediaan fisik barang dan ambang batas minimum stok di Gudang
            Utama.</p>
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

        <div class="relative sm:w-56">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                <i class="ti ti-filter text-base"></i>
            </span>
            <select name="komponen"
                class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition appearance-none cursor-pointer">
                <option value="0">Semua Komponen</option>
                <?php foreach ($komponenList as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $filterKomponen === (int) $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['kode_komponen'] . ' — ' . $k['nama_komponen']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2 shrink-0">
            <button type="submit"
                class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-sm hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap">
                <i class="ti ti-search mr-1"></i> Cari
            </button>
            <?php if (!empty($searchValue) || $filterKomponen > 0): ?>
                <a href="?"
                    class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-all duration-200 whitespace-nowrap">
                    <i class="ti ti-x mr-1"></i> Reset
                </a>
            <?php endif; ?>
        </div>

    </form>

    <?php if ($filterKomponen > 0): ?>
        <?php
        $aktif = '';
        foreach ($komponenList as $k) {
            if ((int) $k['id'] === $filterKomponen) {
                $aktif = $k['kode_komponen'] . ' — ' . $k['nama_komponen'];
                break;
            }
        }
        ?>
        <div class="mb-4 flex items-center gap-2">
            <span class="text-xs text-slate-500">Filter aktif:</span>
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                <i class="ti ti-filter text-xs"></i>
                <?= htmlspecialchars($aktif) ?>
                <a href="?<?= !empty($searchValue) ? 'search=' . urlencode($searchValue) : '' ?>"
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