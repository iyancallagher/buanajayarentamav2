<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$userId       = $_SESSION['user_id'];
$searchValue  = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'semua'; // semua | draft | setuju | tolak

// ===== Statistik ringkasan (khusus pengajuan milik user ini) =====
$statSql = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'setuju' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as menunggu,
        SUM(CASE WHEN status = 'tolak' THEN 1 ELSE 0 END) as ditolak
    FROM pengajuan_sparepart
    WHERE user_id = ?
";
$statStmt = mysqli_prepare($conn, $statSql);
mysqli_stmt_bind_param($statStmt, 'i', $userId);
mysqli_stmt_execute($statStmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($statStmt)) ?: [];

// ===== Setup pagination =====
$perPage     = 9;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ===== Filter status (opsional) =====
$statusMap = [
    'draft'  => 'draft',
    'setuju' => 'setuju',
    'tolak'  => 'tolak',
];
$activeStatus = $statusMap[$statusFilter] ?? null;

// ===== Hitung total data untuk pagination =====
$countSql = "
    SELECT COUNT(*) as total
    FROM pengajuan_sparepart p
    JOIN sparepart s ON p.sparepart_id = s.id
    WHERE p.user_id = ?
";
$countParams = [$userId];
$countTypes  = 'i';

if (!empty($searchValue)) {
    $countSql .= " AND (s.nama_sparepart LIKE ? OR s.kode_sparepart LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countTypes   .= 'ss';
}

if ($activeStatus !== null) {
    $countSql .= " AND p.status = ?";
    $countParams[] = $activeStatus;
    $countTypes   .= 's';
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows   = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama =====
$sql = "
    SELECT p.id, p.created_at, p.updated_at, p.quantity,
           p.foto_sparepart, p.kondisi_sparepart, p.keterangan,
           p.status, p.surat_jalan_id,
           s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit, k.kode_komponen
    FROM pengajuan_sparepart p
    JOIN sparepart s ON p.sparepart_id = s.id
    LEFT JOIN komponen k ON s.komponen_id = k.id
    WHERE p.user_id = ?
";

$params = [$userId];
$types  = 'i';

if (!empty($searchValue)) {
    $sql .= " AND (s.nama_sparepart LIKE ? OR s.kode_sparepart LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $params[] = $likeValue;
    $params[] = $likeValue;
    $types   .= 'ss';
}

if ($activeStatus !== null) {
    $sql .= " AND p.status = ?";
    $params[] = $activeStatus;
    $types   .= 's';
}

$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ===== Susun data jadi array siap pakai untuk card =====
$submissions = [];
while ($data = mysqli_fetch_assoc($result)) {

    $badgeColor  = 'slate';
    $statusLabel = ucfirst($data['status']);
    if ($data['status'] === 'draft') {
        $badgeColor  = 'yellow';
        $statusLabel = 'Menunggu';
    } elseif ($data['status'] === 'setuju') {
        $badgeColor  = 'green';
        $statusLabel = 'Disetujui';
    } elseif ($data['status'] === 'tolak') {
        $badgeColor  = 'red';
        $statusLabel = 'Ditolak';
    }

    // Format nama gabungan, konsisten dengan halaman lain
    $typeUnitArray   = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    $typeUnitText   = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = htmlspecialchars($data['nama_sparepart']);
    if (!empty($typeUnitText))   $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    if (!empty($numberPartText)) $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode_sparepart'])
        : htmlspecialchars($data['kode_sparepart']);

    // Pemrosesan array foto
    $fotoList = [];
    if (!empty($data['foto_sparepart'])) {
        $decodedFoto = json_decode($data['foto_sparepart'], true);
        if (is_array($decodedFoto)) {
            foreach ($decodedFoto as $foto) {
                if (empty($foto)) continue;

                if (strpos($foto, 'data:') === 0 || strpos($foto, 'http') === 0) {
                    $fotoList[] = $foto;
                } elseif (strpos($foto, 'uploads/') !== false) {
                    $fotoList[] = '../' . ltrim($foto, '/');
                } else {
                    $fotoList[] = 'data:image/jpeg;base64,' . $foto;
                }
            }
        }
    }

    $submissions[] = [
        'id'             => (int) $data['id'],
        'tanggal'        => date('d M Y', strtotime($data['created_at'])),
        'jam'            => date('H:i', strtotime($data['created_at'])),
        'nama'           => $namaLengkap,
        'kode'           => $kodeGabungan,
        'qty'            => (int) $data['quantity'],
        'kondisi'        => !empty($data['kondisi_sparepart']) ? htmlspecialchars($data['kondisi_sparepart']) : null,
        'keterangan'     => !empty($data['keterangan']) ? htmlspecialchars($data['keterangan']) : null,
        'foto'           => $fotoList,
        'surat_jalan_id' => $data['surat_jalan_id'] !== null ? (int) $data['surat_jalan_id'] : null,
        'status_raw'     => $data['status'],
        'status_label'   => $statusLabel,
        'status_color'   => $badgeColor,
    ];
}

$baseQuery = [];
if (!empty($searchValue)) {
    $baseQuery['search'] = $searchValue;
}
if ($statusFilter !== 'semua') {
    $baseQuery['status'] = $statusFilter;
}

// Helper kecil untuk bikin URL filter status tanpa mengganggu search/page
function filterUrl(string $status, string $currentSearch): string
{
    $q = [];
    if (!empty($currentSearch)) $q['search'] = $currentSearch;
    if ($status !== 'semua')    $q['status'] = $status;
    return empty($q) ? '?' : '?' . http_build_query($q);
}

?>
<main class="p-4 sm:p-6 lg:p-8 bg-slate-50 min-h-screen" x-data="{ previewIndex: null, previewList: [] }">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-7">
        <div>
            <p class="text-xs font-semibold text-blue-600 mb-1 tracking-widest uppercase">
                Workshop Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-900">
                Riwayat Pengajuan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pantau status pengajuan sparepart ke warehouse.
            </p>
        </div>

        <a href="pengajuan-sparepart/create-pengajuan.php"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 active:bg-blue-800 transition-colors whitespace-nowrap">
            <i class="ti ti-plus text-base"></i>
            Buat Pengajuan
        </a>
    </div>

    <!-- Statistik -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <i class="ti ti-folders text-xl text-blue-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Total Pengajuan</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight">
                    <?= (int) ($stats['total'] ?? 0) ?>
                </h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
                <i class="ti ti-circle-check text-xl text-green-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Disetujui</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight">
                    <?= (int) ($stats['disetujui'] ?? 0) ?>
                </h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center shrink-0">
                <i class="ti ti-clock-hour-4 text-xl text-yellow-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Menunggu</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight">
                    <?= (int) ($stats['menunggu'] ?? 0) ?>
                </h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <i class="ti ti-circle-x text-xl text-red-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Ditolak</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight">
                    <?= (int) ($stats['ditolak'] ?? 0) ?>
                </h3>
            </div>
        </div>

    </div>

    <!-- Toolbar: filter status + search -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-5">

        <!-- Filter status (chip/tab) -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mb-1">
            <a href="<?= filterUrl('semua', $searchValue) ?>"
                class="px-3.5 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                       <?= $statusFilter === 'semua' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                Semua
            </a>
            <a href="<?= filterUrl('draft', $searchValue) ?>"
                class="px-3.5 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                       <?= $statusFilter === 'draft' ? 'bg-yellow-500 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                Menunggu
            </a>
            <a href="<?= filterUrl('setuju', $searchValue) ?>"
                class="px-3.5 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                       <?= $statusFilter === 'setuju' ? 'bg-green-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                Disetujui
            </a>
            <a href="<?= filterUrl('tolak', $searchValue) ?>"
                class="px-3.5 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                       <?= $statusFilter === 'tolak' ? 'bg-red-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                Ditolak
            </a>
        </div>

        <!-- Search -->
        <form method="GET" class="relative w-full lg:w-80 shrink-0">
            <?php if ($statusFilter !== 'semua'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($searchValue) ?>"
                placeholder="Cari nama atau kode sparepart..."
                class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm
                       placeholder:text-slate-400 transition-all
                       focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">
        </form>
    </div>

    <!-- Empty state -->
    <?php if (empty($submissions)): ?>

    <div class="bg-white rounded-2xl border border-slate-200 flex flex-col items-center justify-center py-16 px-6 text-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
            <i class="ti ti-package-off text-2xl text-slate-400"></i>
        </div>
        <p class="font-semibold text-slate-800">Belum ada pengajuan</p>
        <p class="text-sm text-slate-500 mt-1 max-w-xs">
            <?= !empty($searchValue) || $statusFilter !== 'semua'
                ? 'Tidak ada pengajuan yang cocok dengan filter saat ini.'
                : 'Klik "Buat Pengajuan" untuk mulai mengajukan sparepart ke warehouse.' ?>
        </p>
    </div>

    <?php else: ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($submissions as $i => $item): ?>
        <div class="bg-white rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition-all duration-150 flex flex-col overflow-hidden">

            <!-- Foto -->
            <?php if (!empty($item['foto'])): ?>
                <div class="relative w-full aspect-[16/10] bg-slate-900 group cursor-pointer overflow-hidden shrink-0"
                    @click="previewList = <?= htmlspecialchars(json_encode($item['foto']), ENT_QUOTES) ?>; previewIndex = 0">

                    <img src="<?= $item['foto'][0] ?>" loading="lazy"
                        class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300">

                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 via-transparent to-transparent pointer-events-none"></div>

                    <div class="absolute top-2 right-2">
                        <?= renderBadge($item['status_label'], $item['status_color']) ?>
                    </div>

                    <?php if (count($item['foto']) > 1): ?>
                        <span class="absolute bottom-2 right-2 bg-black/60 text-white text-[11px] font-medium px-2 py-1 rounded-md flex items-center gap-1 backdrop-blur-sm">
                            <i class="ti ti-photo text-xs"></i> <?= count($item['foto']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="relative w-full aspect-[16/10] bg-slate-50 flex flex-col items-center justify-center text-slate-300 gap-1 border-b border-slate-100 shrink-0">
                    <i class="ti ti-photo-off text-2xl"></i>
                    <span class="text-[11px] text-slate-400">Tidak ada foto</span>
                    <div class="absolute top-2 right-2">
                        <?= renderBadge($item['status_label'], $item['status_color']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Body -->
            <div class="p-4 flex flex-col flex-1 gap-3">

                <div>
                    <h4 class="font-semibold text-slate-800 text-sm leading-snug line-clamp-2" title="<?= $item['nama'] ?>">
                        <?= $item['nama'] ?>
                    </h4>
                    <p class="font-mono text-[11px] text-slate-400 mt-1 truncate">
                        <?= $item['kode'] ?>
                    </p>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 py-2.5 border-y border-slate-100">
                    <span class="flex items-center gap-1.5">
                        <i class="ti ti-calendar-event text-slate-400"></i>
                        <?= $item['tanggal'] ?> &middot; <?= $item['jam'] ?>
                    </span>
                    <span class="font-semibold text-slate-700">
                        <?= $item['qty'] ?> <span class="text-slate-400 font-normal"></span>
                    </span>
                </div>

                <?php if ($item['keterangan']): ?>
                <p class="text-xs text-slate-500 leading-relaxed line-clamp-2" title="<?= $item['keterangan'] ?>">
                    <i class="ti ti-notes text-slate-400 mr-1"></i><?= $item['keterangan'] ?>
                </p>
                <?php endif; ?>

                <?php if ($item['surat_jalan_id']): ?>
                <div class="mt-auto pt-2 flex items-center gap-1.5 text-xs font-medium text-blue-600">
                    <i class="ti ti-truck-delivery text-sm"></i>
                    Surat Jalan #<?= $item['surat_jalan_id'] ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <!-- Pagination -->
    <div class="mt-6">
        <?php include '../components/pagination.php'; ?>
    </div>

    <!-- Modal preview foto -->
    <div
        x-show="previewIndex !== null"
        x-cloak
        x-transition.opacity
        @click.self="previewIndex = null"
        @keydown.escape.window="previewIndex = null"
        @keydown.arrow-right.window="if (previewIndex !== null && previewList.length) previewIndex = (previewIndex + 1) % previewList.length"
        @keydown.arrow-left.window="if (previewIndex !== null && previewList.length) previewIndex = (previewIndex - 1 + previewList.length) % previewList.length"
        class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-50 flex items-center justify-center p-4">

        <button
            @click="previewIndex = null"
            class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
            <i class="ti ti-x text-xl"></i>
        </button>

        <template x-if="previewList.length > 1">
            <button
                @click="previewIndex = (previewIndex - 1 + previewList.length) % previewList.length"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <i class="ti ti-chevron-left text-xl"></i>
            </button>
        </template>

        <template x-if="previewList.length > 1">
            <button
                @click="previewIndex = (previewIndex + 1) % previewList.length"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <i class="ti ti-chevron-right text-xl"></i>
            </button>
        </template>

        <img :src="previewList[previewIndex]" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain">

        <template x-if="previewList.length > 1">
            <span class="absolute bottom-5 left-1/2 -translate-x-1/2 bg-black/60 text-white text-xs font-medium px-3 py-1.5 rounded-full backdrop-blur-sm"
                x-text="(previewIndex + 1) + ' / ' + previewList.length"></span>
        </template>

    </div>
</main>

<?php include '../layouts/footer.php'; ?>