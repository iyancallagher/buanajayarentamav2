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
$perPage     = 100;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords  = explode(' ', trim($searchValue));
    $searchWords  = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Hitung total data dulu (untuk pagination) =====
$countSql     = "SELECT COUNT(*) as total FROM sparepart s WHERE s.deleted_at IS NULL";
$countParams = [];
$countTypes  = '';

if (!empty($searchValue)) {
    $countSql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $countParams[] = $booleanQuery;
    $countTypes   .= 's';
}

$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($countParams)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama dengan FULLTEXT & LEFT JOIN =====
$sql = "
    SELECT s.id, 
           s.nama_sparepart as nama, 
           s.kode_sparepart as kode, 
           s.number_part, 
           s.type_unit, 
           s.created_at, 
           k.kode_komponen
";

if (!empty($searchValue)) {
    $sql .= ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
}

$sql .= "
    FROM sparepart s 
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE s.deleted_at IS NULL
";

$params = [];
$types  = '';

if (!empty($searchValue)) {
    $params[] = $booleanQuery;
    $types   .= 's';

    $sql .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
    $params[] = $booleanQuery;
    $types   .= 's';

    $sql .= " ORDER BY relevance_score DESC, s.created_at DESC";
} else {
    $sql .= " ORDER BY s.created_at DESC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
$no = $offset + 1;
while ($data = mysqli_fetch_assoc($result)) {

    // 1. Decode JSON array, fallback ke array kosong kalau null/invalid
    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    // 2. Gabungkan jadi string dipisah " / " atau "/"
    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode(' / ', $numberPartArray);

    // 3. Susun format gabungan nama sparepart (menggunakan htmlspecialchars untuk keamanan)
    $namaLengkap = htmlspecialchars($data['nama']);

    if (!empty($typeUnitText)) {
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    }

    if (!empty($numberPartText)) {
        $namaLengkap .= ' / ' . htmlspecialchars($numberPartText);
    }

    // 4. Gabungkan kode_komponen + kode_sparepart
    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode'])
        : htmlspecialchars($data['kode']);

    $actionButtons = '
        <div class="flex items-center justify-center gap-2">
            <a href="' . BASE_URL . '/warehouse/sparepart/edit-sparepart.php?id=' . $data['id'] . '"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50 transition-colors"
                title="Edit">
                <i class="ti ti-pencil text-base"></i>
            </a>
            <button
                type="button"
                @click="confirmDelete(' . $data['id'] . ', \'' . htmlspecialchars($data['nama'], ENT_QUOTES) . '\')"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition-colors"
                title="Hapus">
                <i class="ti ti-trash text-base"></i>
            </button>
        </div>
    ';

    $rows[] = [
        'no'   => $no++,
        'kode' => $kodeGabungan,
        'nama' => $namaLengkap,
        'aksi' => $actionButtons,
    ];
}

// ===== Struktur kolom disamakan dengan Stok Sparepart =====
$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Kode Sparepart', 'key' => 'kode'],
    ['label' => 'Nama Sparepart', 'key' => 'nama'],
    ['label' => 'Aksi', 'key' => 'aksi', 'align' => 'center', 'raw' => true],
];

$tableTitle = 'Daftar Sparepart';
$emptyMessage = 'Belum ada data sparepart.';
$tableActions = '<a href="' . BASE_URL . '/warehouse/sparepart/create-sparepart.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap"><i class="ti ti-plus text-base"></i> Tambah Sparepart</a>';

// ===== Variabel untuk sparepart pagination =====
$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}

?>
<main class="p-6 bg-slate-50 min-h-screen" x-data="{
    showDeleteModal: false,
    deleteId: null,
    deleteName: '',
    confirmDelete(id, name) {
        this.deleteId = id;
        this.deleteName = name;
        this.showDeleteModal = true;
    }
}">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Daftar Sparepart</h1>
        <p class="text-slate-500 mt-1">Kelola data master sparepart.</p>
    </div>

<?php if (!empty($_SESSION['form_success'])): ?>
        <div
            class="mb-6 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2">
            <i class="ti ti-circle-check text-base shrink-0"></i>
            <?= htmlspecialchars($_SESSION['form_success']) ?>
        </div>
        <?php unset($_SESSION['form_success']); endif; ?>

    <?php if (!empty($_SESSION['form_error'])): ?>
        <div
            class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
            <i class="ti ti-alert-circle text-base shrink-0"></i>
            <?= htmlspecialchars($_SESSION['form_error']) ?>
        </div>
        <?php unset($_SESSION['form_error']); endif; ?>

    <?php include '../components/table.php'; ?>
    <?php include '../components/pagination.php'; ?>

    <div x-show="showDeleteModal" x-cloak x-transition.opacity
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showDeleteModal = false">

        <div x-show="showDeleteModal" x-transition.scale.95
            class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 mx-auto">

            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mb-4 mx-auto">
                <i class="ti ti-alert-triangle text-xl text-red-500"></i>
            </div>

            <h3 class="text-lg font-semibold text-slate-800 text-center mb-2">
                Hapus Sparepart?
            </h3>

            <p class="text-sm text-slate-500 text-center mb-6 px-2 leading-relaxed">
                Apakah kamu yakin ingin menghapus sparepart <strong class="text-slate-800 word-break"
                    x-text="deleteName"></strong>? Tindakan ini tidak dapat dibatalkan.
            </p>

            <form :action="'<?= BASE_URL ?>/warehouse/sparepart/delete-sparepart.php?id=' + deleteId" method="POST">
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="showDeleteModal = false"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-colors text-sm text-center">
                        Batal
                    </button>
                    <button type="submit"
                        class="w-full px-4 py-3 rounded-xl bg-red-500 text-white font-semibold hover:bg-red-600 transition-colors text-sm text-center shadow-lg shadow-red-500/20">
                        Ya, Hapus
                    </button>
                </div>
            </form>

        </div>
    </div>

</main>

<?php include '../layouts/footer.php'; ?>