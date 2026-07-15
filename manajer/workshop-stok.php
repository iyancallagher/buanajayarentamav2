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
$filterWorkshop = (int) ($_GET['workshop'] ?? 0);
$filterKomponen = (int) ($_GET['komponen'] ?? 0);

// ===== Ambil daftar workshop (kepala workshop) untuk dropdown =====
$workshopList = [];
$resWorkshop = mysqli_query($conn, "
    SELECT id, nama FROM users WHERE role = 'kepala workshop' ORDER BY nama ASC
");
while ($w = mysqli_fetch_assoc($resWorkshop)) {
    $workshopList[] = $w;
}

// ===== Ambil daftar komponen untuk dropdown =====
$komponenList = [];
$resKomponen = mysqli_query($conn, "SELECT id, kode_komponen, nama_komponen FROM komponen ORDER BY nama_komponen ASC");
while ($k = mysqli_fetch_assoc($resKomponen)) {
    $komponenList[] = $k;
}

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Pagination =====
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// Untuk count kita rebuild karena strukturnya berbeda per mode
if ($filterWorkshop > 0) {
    // Mode: 1 workshop — 1 baris per sparepart
    $countStmt = mysqli_prepare(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM sparepart s
        LEFT JOIN komponen k ON k.id = s.komponen_id
        JOIN stok_sparepart_wk sw ON sw.sparepart_id = s.id AND sw.user_id = ?
        WHERE s.deleted_at IS NULL
        " . (!empty($booleanQuery) ? " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)" : "") . "
        " . ($filterKomponen > 0 ? " AND s.komponen_id = ?" : "")
    );

    $cp = [$filterWorkshop];
    $ct = 'i';
    if (!empty($booleanQuery)) {
        $cp[] = $booleanQuery;
        $ct .= 's';
    }
    if ($filterKomponen > 0) {
        $cp[] = $filterKomponen;
        $ct .= 'i';
    }
    mysqli_stmt_bind_param($countStmt, $ct, ...$cp);
} else {
    // Mode: semua workshop — 1 baris per sparepart × workshop
    $countStmt = mysqli_prepare(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM sparepart s
        LEFT JOIN komponen k ON k.id = s.komponen_id
        CROSS JOIN (SELECT id FROM users WHERE role = 'kepala workshop') u
        JOIN stok_sparepart_wk sw ON sw.sparepart_id = s.id AND sw.user_id = u.id
        WHERE s.deleted_at IS NULL
        " . (!empty($booleanQuery) ? " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)" : "") . "
        " . ($filterKomponen > 0 ? " AND s.komponen_id = ?" : "")
    );

    $cp = [];
    $ct = '';
    if (!empty($booleanQuery)) {
        $cp[] = $booleanQuery;
        $ct .= 's';
    }
    if ($filterKomponen > 0) {
        $cp[] = $filterKomponen;
        $ct .= 'i';
    }
    if (!empty($cp)) {
        mysqli_stmt_bind_param($countStmt, $ct, ...$cp);
    }
}
mysqli_stmt_execute($countStmt);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];

// ===== Query utama =====
$extraWhere = "WHERE s.deleted_at IS NULL";
$params = [];
$types = '';

if ($filterWorkshop > 0) {
    $extraWhere .= " AND sw.user_id = ?";
    $params[] = $filterWorkshop;
    $types .= 'i';
}
if ($filterKomponen > 0) {
    $extraWhere .= " AND s.komponen_id = ?";
    $params[] = $filterKomponen;
    $types .= 'i';
}
if (!empty($booleanQuery)) {
    $extraWhere .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $params[] = $booleanQuery;
    $types .= 's';
}

// Persiapkan array parameter utama data (Limit dan Offset)
$paginationParams = [$perPage, $offset];
$paginationTypes = 'ii';

// Atur select relevance score dan klausul pengurutan data secara dinamis
$relevanceSelect = "";
$orderByClause = "";

if (!empty($booleanQuery)) {
    $relevanceSelect = ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
    if ($filterWorkshop > 0) {
        $orderByClause = "ORDER BY relevance_score DESC, (sw.stok = 0) ASC, sw.stok ASC, s.nama_sparepart ASC";
    } else {
        $orderByClause = "ORDER BY relevance_score DESC, u.nama ASC, (sw.stok = 0) ASC, sw.stok ASC, s.nama_sparepart ASC";
    }
} else {
    if ($filterWorkshop > 0) {
        $orderByClause = "ORDER BY (sw.stok = 0) ASC, sw.stok ASC, s.nama_sparepart ASC";
    } else {
        $orderByClause = "ORDER BY u.nama ASC, (sw.stok = 0) ASC, sw.stok ASC, s.nama_sparepart ASC";
    }
}

if ($filterWorkshop > 0) {
    $sql = "
        SELECT s.kode_sparepart AS kode,
               s.nama_sparepart AS nama,
               s.number_part,
               s.type_unit,
               k.kode_komponen,
               k.nama_komponen,
               u.nama           AS nama_workshop,
               sw.stok          AS stok
               $relevanceSelect
        FROM sparepart s
        LEFT JOIN komponen k ON k.id = s.komponen_id
        JOIN users u ON u.id = ?
        JOIN stok_sparepart_wk sw ON sw.sparepart_id = s.id AND sw.user_id = ?
        $extraWhere
        $orderByClause
        LIMIT ? OFFSET ?
    ";

    $finalParams = [];
    $finalTypes = '';

    if (!empty($booleanQuery)) {
        $finalParams[] = $booleanQuery;
        $finalTypes .= 's';
    }

    $finalParams = array_merge($finalParams, [$filterWorkshop, $filterWorkshop], $params, $paginationParams);
    $finalTypes .= 'ii' . $types . $paginationTypes;
} else {
    $sql = "
        SELECT s.kode_sparepart AS kode,
               s.nama_sparepart AS nama,
               s.number_part,
               s.type_unit,
               k.kode_komponen,
               k.nama_komponen,
               u.nama           AS nama_workshop,
               sw.stok          AS stok
               $relevanceSelect
        FROM sparepart s
        LEFT JOIN komponen k ON k.id = s.komponen_id
        CROSS JOIN (SELECT id, nama FROM users WHERE role = 'kepala workshop') u
        JOIN stok_sparepart_wk sw ON sw.sparepart_id = s.id AND sw.user_id = u.id
        $extraWhere
        $orderByClause
        LIMIT ? OFFSET ?
    ";

    $finalParams = [];
    $finalTypes = '';

    if (!empty($booleanQuery)) {
        $finalParams[] = $booleanQuery;
        $finalTypes .= 's';
    }

    $finalParams = array_merge($finalParams, $params, $paginationParams);
    $finalTypes .= $types . $paginationTypes;
}

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $finalTypes, ...$finalParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$LOW_STOCK = 5;
$rows = [];
$no = $offset + 1;
while ($data = mysqli_fetch_assoc($result)) {
    $stok = (int) $data['stok'];

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

    if ($stok <= 0) {
        $stokBadge = '<div class="text-center">' . renderBadge('Habis', 'red') . '</div>';
    } elseif ($stok <= $LOW_STOCK) {
        $stokBadge = '<div class="text-center">' . renderBadge($stok , 'yellow') . '</div>';
    } else {
        $stokBadge = '<div class="text-center">' . renderBadge($stok , 'green') . '</div>';
    }

    $rows[] = [
        'no' => $no++,
        'kode' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'komponen' => '<div class="text-sm text-slate-600 text-center">' . htmlspecialchars($data['nama_komponen'] ?? '-') . '</div>',
        'workshop' => '<div class="text-sm text-slate-700 text-center">' . htmlspecialchars($data['nama_workshop']) . '</div>',
        'stok' => $stokBadge,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Kode Sparepart', 'key' => 'kode', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama', 'raw' => true],
    ['label' => 'Komponen', 'key' => 'komponen', 'raw' => true, 'align' => 'center'],
    ['label' => 'Workshop', 'key' => 'workshop', 'raw' => true, 'align' => 'center'],
    ['label' => 'Stok', 'key' => 'stok', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Monitoring Stok Sparepart Workshop';
$emptyMessage = 'Data stok tidak ditemukan.';
$tableActions = '';
$showSearch = false;

$baseQuery = array_filter([
    'search' => $searchValue,
    'workshop' => $filterWorkshop ?: null,
    'komponen' => $filterKomponen ?: null,
]);

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Manajer Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Stok Workshop</h1>
        <p class="text-slate-500 mt-1">Monitoring ketersediaan stok sparepart di seluruh workshop.</p>
    </div>

    <form method="GET" action=""
        class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center flex-wrap">

        <div class="relative flex-1 min-w-48">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                <i class="ti ti-search text-base"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($searchValue) ?>"
                placeholder="Cari nama, kode, number part..."
                class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition" />
        </div>

        <div class="relative sm:w-52">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                <i class="ti ti-building-factory-2 text-base"></i>
            </span>
            <select name="workshop"
                class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition appearance-none cursor-pointer">
                <option value="0">Semua Workshop</option>
                <?php foreach ($workshopList as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $filterWorkshop === (int) $w['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($w['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="relative sm:w-52">
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
            <?php if (!empty($searchValue) || $filterWorkshop > 0 || $filterKomponen > 0): ?>
                <a href="?"
                    class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-all duration-200 whitespace-nowrap">
                    <i class="ti ti-x mr-1"></i> Reset
                </a>
            <?php endif; ?>
        </div>

    </form>

    <?php if ($filterWorkshop > 0 || $filterKomponen > 0): ?>
        <div class="mb-4 flex items-center gap-2 flex-wrap">
            <span class="text-xs text-slate-500">Filter aktif:</span>

            <?php if ($filterWorkshop > 0):
                $namaWs = '';
                foreach ($workshopList as $w) {
                    if ((int) $w['id'] === $filterWorkshop) {
                        $namaWs = $w['nama'];
                        break;
                    }
                }
                ?>
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                    <i class="ti ti-building-factory-2 text-xs"></i>
                    <?= htmlspecialchars($namaWs) ?>
                    <a href="?<?= http_build_query(array_filter(['search' => $searchValue, 'komponen' => $filterKomponen ?: null])) ?>"
                        class="ml-1 text-blue-400 hover:text-blue-600"><i class="ti ti-x text-xs"></i></a>
                </span>
            <?php endif; ?>

            <?php if ($filterKomponen > 0):
                $namaKomp = '';
                foreach ($komponenList as $k) {
                    if ((int) $k['id'] === $filterKomponen) {
                        $namaKomp = $k['kode_komponen'] . ' — ' . $k['nama_komponen'];
                        break;
                    }
                }
                ?>
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                    <i class="ti ti-filter text-xs"></i>
                    <?= htmlspecialchars($namaKomp) ?>
                    <a href="?<?= http_build_query(array_filter(['search' => $searchValue, 'workshop' => $filterWorkshop ?: null])) ?>"
                        class="ml-1 text-blue-400 hover:text-blue-600"><i class="ti ti-x text-xs"></i></a>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>
</main>
<?php include '../layouts/footer.php'; ?>