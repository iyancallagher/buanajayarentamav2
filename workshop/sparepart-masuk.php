<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// Ambil ID user yang sedang login dari session
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

// ===== Hitung total data (Filter berdasarkan user_id & FULLTEXT) =====
$countSql = "
    SELECT COUNT(*) as total
    FROM sparepart_masuk_wk m
    JOIN sparepart s ON s.id = m.sparepart_id
    WHERE m.user_id = ?
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

// ===== Query data utama dengan LEFT JOIN ke komponen =====
$sql = "
    SELECT m.id, m.quantity, m.created_at,
           s.kode_sparepart as kode,
           s.nama_sparepart as nama,
           s.number_part,
           s.type_unit,
           k.kode_komponen
";

if (!empty($booleanQuery)) {
    $sql .= ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
}

$sql .= "
    FROM sparepart_masuk_wk m
    JOIN sparepart s ON s.id = m.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE m.user_id = ?
";

$params = [];
$types = '';

// Ikat parameter relevance score pada SELECT jika sedang mencari
if (!empty($booleanQuery)) {
    $params[] = $booleanQuery;
    $types .= 's';
}

// Ikat parameter wajib untuk WHERE m.user_id
$params[] = $userId;
$types .= 'i';

// Kondisi WHERE filter pencarian dan ORDER BY
if (!empty($booleanQuery)) {
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

    // 1. Decode JSON array dari database
    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    // 2. Gabungkan isi JSON menggunakan separator garis miring
    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    // 3. Susun Nama Lengkap persis format gabungan
    $namaLengkap = htmlspecialchars($data['nama']);

    if (!empty($typeUnitText)) {
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    }

    if (!empty($numberPartText)) {
        $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);
    }

    // 4. Susun Format Kode Gabungan Komponen-Sparepart
    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    $tanggal = date('d M Y', strtotime($data['created_at']));

    $rows[] = [
        'no' => $no++,
        'tanggal' => $tanggal,
        'kode_sparepart' => '<span class="font-mono text-xs text-slate-500">' . $kodeGabungan . '</span>',
        'nama_sparepart' => '<div class="font-medium text-slate-800 leading-relaxed">' . $namaLengkap . '</div>',
        'quantity' => '<div class="text-center font-semibold text-green-600">+' . number_format($data['quantity'], 0, ',', '.') . '</div>',
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Tanggal', 'key' => 'tanggal'],
    ['label' => 'Kode Sparepart', 'key' => 'kode_sparepart', 'raw' => true],
    ['label' => 'Nama Sparepart', 'key' => 'nama_sparepart', 'raw' => true],
    ['label' => 'Jumlah Masuk', 'key' => 'quantity', 'raw' => true, 'align' => 'center'],
];

$tableTitle = 'Sparepart Masuk';
$emptyMessage = 'Belum ada data sparepart masuk.';

$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Workshop Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Sparepart Masuk</h1>
        <p class="text-slate-500 mt-1">Riwayat penerimaan sparepart ke workshop.</p>
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

<?php include '../layouts/footer.php'; ?>