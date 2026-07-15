<?php

require_once '../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$userId = $_SESSION['user_id'];
$searchValue = $_GET['search'] ?? '';

// ===== Setup pagination =====
$perPage = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ===== Hitung total data dulu (untuk pagination), pakai query terpisah tanpa LIMIT =====
$countSql = "SELECT COUNT(*) as total FROM users u WHERE u.id != ? AND u.role IN ('kepala workshop')";
$countParams = [$userId];
$countTypes = 'i';

if (!empty($searchValue)) {
    $countSql .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countTypes .= 'ss';
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data dengan LIMIT/OFFSET =====
$sql = "
    SELECT u.id, u.nama, u.email, u.role, u.created_at
    FROM users u WHERE u.id != ? AND u.role IN ('kepala workshop')
";

$params = [$userId];
$types = 'i';

if (!empty($searchValue)) {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $params[] = $likeValue;
    $params[] = $likeValue;
    $types .= 'ss';
}

$sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
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

    $actionButtons = '
        <div class="flex items-center justify-center gap-2">
            <a href="' . BASE_URL . '/manajer/user/edit-user.php?id=' . $data['id'] . '"
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
        'no' => $no++,
        'nama' => $data['nama'],
        'email' => $data['email'],
        'role' => $data['role'],
        'created_at' => $data['created_at'],
        'aksi' => $actionButtons,
    ];
}

$columns = [
    ['label' => 'No.', 'key' => 'no', 'align' => 'center'],
    ['label' => 'Nama User', 'key' => 'nama'],
    ['label' => 'Email', 'key' => 'email'],
    ['label' => 'Role', 'key' => 'role'],
    ['label' => 'Aksi', 'key' => 'aksi', 'align' => 'center', 'raw' => true],
];

$tableTitle = 'Daftar User';
$emptyMessage = 'Belum ada data user untuk workshop ini.';
$tableActions = '<a href="' . BASE_URL . '/manajer/user/create-user.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap"><i class="ti ti-plus text-base"></i> Tambah User</a>';

// ===== Variabel untuk komponen pagination =====
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
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Manajer Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Daftar workshop</h1>
        <p class="text-slate-500 mt-1">Daftar workshop yang terdaftar.</p>
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

    <!-- Modal Konfirmasi Delete -->
    <div x-show="showDeleteModal" x-cloak x-transition.opacity
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showDeleteModal = false">

        <div x-show="showDeleteModal" x-transition.scale.95 class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">

            <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mb-4 mx-auto">
                <i class="ti ti-alert-triangle text-2xl text-red-500"></i>
            </div>

            <h3 class="text-lg font-semibold text-slate-800 text-center mb-2">
                Hapus User?
            </h3>

            <p class="text-sm text-slate-500 text-center mb-6">
                Apakah kamu yakin ingin menghapus user <strong x-text="deleteName"></strong>? Tindakan ini tidak dapat
                dibatalkan.
            </p>

            <div class="flex gap-3">
                <button type="button" @click="showDeleteModal = false"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition-colors">
                    Batal
                </button>

                <form :action="'<?= BASE_URL ?>/manajer/user/delete-user.php?id=' + deleteId" method="POST"
                    class="flex-1">
                    <button type="submit"
                        class="w-full px-4 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors">
                        Ya, Hapus
                    </button>
                </form>
            </div>

        </div>

    </div>

</main>

<?php include '../layouts/footer.php'; ?>