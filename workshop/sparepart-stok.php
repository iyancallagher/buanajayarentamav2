<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$userId = $_SESSION['user_id'];
$searchValue = $_GET['search'] ?? '';

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords = explode(' ', trim($searchValue));
    $searchWords = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Setup pagination =====
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ===== Hitung total data untuk pagination =====
$countSql = "
    SELECT COUNT(*) as total
    FROM sparepart s
    LEFT JOIN stok_sparepart_wk sw ON s.id = sw.sparepart_id AND sw.user_id = ?
    WHERE s.deleted_at IS NULL
";
$countParams = [$userId];
$countTypes = 'i';

if (!empty($booleanQuery)) {
    $countSql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $countParams[] = $booleanQuery;
    $countTypes .= 's';
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama =====
$sql = "
    SELECT s.id, s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit,
           k.kode_komponen, k.nama_komponen,
           IFNULL(sw.stok, 0) as stok
";

if (!empty($booleanQuery)) {
    $sql .= ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
}

$sql .= "
    FROM sparepart s
    LEFT JOIN stok_sparepart_wk sw ON s.id = sw.sparepart_id AND sw.user_id = ?
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE s.deleted_at IS NULL
";

$params = [];
$types = '';

// Ikat parameter relevance score pada SELECT jika sedang mencari
if (!empty($booleanQuery)) {
    $params[] = $booleanQuery;
    $types .= 's';
}

// Ikat parameter wajib untuk LEFT JOIN sw.user_id
$params[] = $userId;
$types .= 'i';

// Kondisi WHERE dan ORDER BY menyesuaikan status pencarian
if (!empty($booleanQuery)) {
    $sql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $params[] = $booleanQuery;
    $types .= 's';

    $sql .= " ORDER BY relevance_score DESC, (IFNULL(sw.stok, 0) = 0) ASC, IFNULL(sw.stok, 0) ASC, s.nama_sparepart ASC";
} else {
    $sql .= " ORDER BY (IFNULL(sw.stok, 0) = 0) ASC, IFNULL(sw.stok, 0) ASC, s.nama_sparepart ASC";
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

    // 1. Decode JSON array dari database
    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    // 2. Gabungkan isi JSON menggunakan separator garis miring
    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    // 3. Susun Nama Lengkap persis format gabungan
    $namaLengkap = htmlspecialchars($data['nama_sparepart']);

    if (!empty($typeUnitText)) {
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    }

    if (!empty($numberPartText)) {
        $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);
    }

    // 4. Susun Format Kode Gabungan Komponen-Sparepart
    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode_sparepart'])
        : htmlspecialchars($data['kode_sparepart']);

    // 5. Logika warna badge stok: 2 kondisi saja
    if ($stok <= 0) {
        $stokBadge = '<div class="text-center">' . renderBadge('Habis', 'red') . '</div>';
    } else {
        $stokBadge = '<div class="text-center">' . renderBadge($stok . ' Pcs', 'green') . '</div>';
    }

    $rows[] = [
        'no' => $no++,
        'kode' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'stok' => $stokBadge,
    ];
}

// Skema kolom tabel (disamakan dengan warehouse, minus Batas Minimum)
$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Kode Sparepart', 'key' => 'kode', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama', 'raw' => true],
    ['label' => 'Jumlah Stok', 'key' => 'stok', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Stok Sparepart';
$emptyMessage = 'Belum ada data stok sparepart untuk workshop ini.';
$tableActions = '<a href="pengajuan-sparepart/create-pengajuan.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap"><i class="ti ti-plus text-base"></i> Pengajuan Sparepart</a>';

$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Workshop Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Stok Sparepart</h1>
        <p class="text-slate-500 mt-1">Daftar stok sparepart yang tersedia di workshop kamu.</p>
    </div>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>

</main>

<?php include '../layouts/footer.php'; ?>