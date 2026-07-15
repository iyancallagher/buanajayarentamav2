<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';

$menuFile = __DIR__ . '/menu.php';

$userId = $_SESSION['user_id'];

// ===== Statistik Pengajuan =====
$stmtStats = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'disetujui') AS disetujui,
        SUM(status = 'draft' OR status = 'menunggu') AS menunggu,
        SUM(status = 'ditolak') AS ditolak
    FROM pengajuan_sparepart
    WHERE user_id = ?
");
mysqli_stmt_bind_param($stmtStats, 'i', $userId);
mysqli_stmt_execute($stmtStats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtStats));

$totalPengajuan = (int)($stats['total']      ?? 0);
$totalDisetujui = (int)($stats['disetujui']  ?? 0);
$totalMenunggu  = (int)($stats['menunggu']   ?? 0);
$totalDitolak   = (int)($stats['ditolak']    ?? 0);

// ===== Stok Menipis (stok > 0 dan <= 5) =====
$stmtMenipis = mysqli_prepare($conn, "
    SELECT COUNT(*) AS jumlah
    FROM sparepart s
    LEFT JOIN stok_sparepart_wk sw ON s.id = sw.sparepart_id AND sw.user_id = ?
    WHERE IFNULL(sw.stok, 0) > 0 AND IFNULL(sw.stok, 0) <= 5
");
mysqli_stmt_bind_param($stmtMenipis, 'i', $userId);
mysqli_stmt_execute($stmtMenipis);
$stokMenipis = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMenipis))['jumlah'] ?? 0);

// ===== Stok Habis =====
$stmtHabis = mysqli_prepare($conn, "
    SELECT COUNT(*) AS jumlah
    FROM sparepart s
    LEFT JOIN stok_sparepart_wk sw ON s.id = sw.sparepart_id AND sw.user_id = ?
    WHERE IFNULL(sw.stok, 0) = 0
");
mysqli_stmt_bind_param($stmtHabis, 'i', $userId);
mysqli_stmt_execute($stmtHabis);
$stokHabis = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtHabis))['jumlah'] ?? 0);

// ===== Total Maintenance =====
$stmtMaint = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM maintenance_wk WHERE user_id = ?");
mysqli_stmt_bind_param($stmtMaint, 'i', $userId);
mysqli_stmt_execute($stmtMaint);
$totalMaintenance = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMaint))['total'] ?? 0);

// ===== Maintenance Terbaru (5 terakhir) =====
$stmtMaintRecent = mysqli_prepare($conn, "
    SELECT id, type_unit, nopol, mekanik, sparepart_list, created_at
    FROM maintenance_wk
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmtMaintRecent, 'i', $userId);
mysqli_stmt_execute($stmtMaintRecent);
$maintRows = mysqli_stmt_get_result($stmtMaintRecent);

// Kumpulkan semua sparepart_id dari hasil maintenance untuk batch query nama sparepart
$maintData = [];
$allSparepartIds = [];
while ($m = mysqli_fetch_assoc($maintRows)) {
    $list = json_decode($m['sparepart_list'] ?? '[]', true) ?: [];
    foreach ($list as $item) {
        $allSparepartIds[] = (int)$item['sparepart_id'];
    }
    $m['sparepart_list_decoded'] = $list;
    $maintData[] = $m;
}

// Batch query nama sparepart supaya tidak N+1
$sparepartNames = [];
if (!empty($allSparepartIds)) {
    $uniqueIds   = array_unique($allSparepartIds);
    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
    $types        = str_repeat('i', count($uniqueIds));
    $stmtNames    = mysqli_prepare($conn, "SELECT id, nama_sparepart FROM sparepart WHERE id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtNames, $types, ...$uniqueIds);
    mysqli_stmt_execute($stmtNames);
    $namesResult = mysqli_stmt_get_result($stmtNames);
    while ($n = mysqli_fetch_assoc($namesResult)) {
        $sparepartNames[$n['id']] = $n['nama_sparepart'];
    }
}

// ===== Aktivitas Terbaru (5 pengajuan terakhir) =====
$stmtRecent = mysqli_prepare($conn, "
    SELECT
        ps.created_at,
        ps.quantity,
        ps.status,
        s.nama_sparepart,
        s.kode_sparepart,
        k.kode_komponen
    FROM pengajuan_sparepart ps
    JOIN sparepart s ON s.id = ps.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE ps.user_id = ?
    ORDER BY ps.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmtRecent, 'i', $userId);
mysqli_stmt_execute($stmtRecent);
$recentRows = mysqli_stmt_get_result($stmtRecent);

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';
?>

<main class="p-6 bg-slate-50 min-h-screen">

    <!-- Page Header -->
    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Workshop Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-slate-500 mt-1">Ringkasan stok dan aktivitas pengajuan sparepart workshop kamu.</p>
    </div>

    <!-- Statistik Pengajuan -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

        <div class="bg-blue-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-blue-100 font-medium">Total Pengajuan</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= $totalPengajuan ?></h3>
                    <p class="text-xs text-blue-100/80 mt-2">Semua waktu</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-clipboard-list text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-green-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-green-100 font-medium">Disetujui</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= $totalDisetujui ?></h3>
                    <p class="text-xs text-green-100/80 mt-2">
                        <?= $totalPengajuan > 0 ? round($totalDisetujui / $totalPengajuan * 100) : 0 ?>% dari total
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-circle-check text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-yellow-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-yellow-100 font-medium">Menunggu Approval</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= $totalMenunggu ?></h3>
                    <p class="text-xs text-yellow-100/80 mt-2">Perlu tindakan</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-clock-hour-4 text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-red-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-red-100 font-medium">Ditolak</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= $totalDitolak ?></h3>
                    <p class="text-xs text-red-100/80 mt-2">Semua waktu</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-circle-x text-2xl text-white"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Quick Menu + Info Stok -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

        <!-- Quick Menu: Stok Sparepart -->
        <a href="sparepart-stok.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-200 hover:-translate-y-0.5 transition-all duration-200">
            <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-500 transition-colors duration-200">
                <i class="ti ti-package text-2xl text-blue-500 group-hover:text-white transition-colors duration-200"></i>
            </div>
            <h3 class="font-semibold text-lg mt-4 text-slate-800">Stok Sparepart</h3>
            <p class="text-sm text-slate-500 mt-2">Lihat ketersediaan stok sparepart di workshop kamu.</p>
            <div class="flex items-center gap-1 text-sm font-medium text-blue-500 mt-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>
        </a>

        <!-- Quick Menu: Pengajuan Sparepart -->
        <a href="pengajuan-sparepart.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-purple-200 hover:-translate-y-0.5 transition-all duration-200">
            <div class="w-14 h-14 rounded-xl bg-purple-50 flex items-center justify-center group-hover:bg-purple-500 transition-colors duration-200">
                <i class="ti ti-clipboard-plus text-2xl text-purple-500 group-hover:text-white transition-colors duration-200"></i>
            </div>
            <h3 class="font-semibold text-lg mt-4 text-slate-800">Pengajuan Sparepart</h3>
            <p class="text-sm text-slate-500 mt-2">Ajukan permintaan sparepart ke gudang.</p>

            <?php if ($totalMenunggu > 0): ?>
                <div class="mt-4">
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                        <?= $totalMenunggu ?> menunggu persetujuan
                    </span>
                </div>
            <?php else: ?>
                <div class="mt-4">
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-400 bg-slate-50 px-2 py-1 rounded-full">
                        Tidak ada yang menunggu
                    </span>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-1 text-sm font-medium text-purple-500 mt-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>
        </a>

        <!-- Quick Menu: Buat Pengajuan Baru -->
        <a href="pengajuan-sparepart/create-pengajuan.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-green-200 hover:-translate-y-0.5 transition-all duration-200">
            <div class="w-14 h-14 rounded-xl bg-green-50 flex items-center justify-center group-hover:bg-green-500 transition-colors duration-200">
                <i class="ti ti-plus text-2xl text-green-500 group-hover:text-white transition-colors duration-200"></i>
            </div>
            <h3 class="font-semibold text-lg mt-4 text-slate-800">Buat Pengajuan Baru</h3>
            <p class="text-sm text-slate-500 mt-2">Buat pengajuan sparepart baru ke gudang sekarang.</p>
            <div class="flex items-center gap-1 text-sm font-medium text-green-500 mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>
        </a>

        <!-- Quick Menu: Maintenance -->
        <a href="maintenance.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-orange-200 hover:-translate-y-0.5 transition-all duration-200">
            <div class="w-14 h-14 rounded-xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-500 transition-colors duration-200">
                <i class="ti ti-tool text-2xl text-orange-500 group-hover:text-white transition-colors duration-200"></i>
            </div>
            <h3 class="font-semibold text-lg mt-4 text-slate-800">Maintenance</h3>
            <p class="text-sm text-slate-500 mt-2">Catat dan kelola aktivitas maintenance unit.</p>
            <div class="mt-4">
                <span class="inline-flex items-center gap-1 text-xs font-medium text-orange-600 bg-orange-50 px-2 py-1 rounded-full border border-orange-100">
                    <?= $totalMaintenance ?> total aktivitas
                </span>
            </div>
            <div class="flex items-center gap-1 text-sm font-medium text-orange-500 mt-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>
        </a>

    </div>

    <!-- Aktivitas Terbaru -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

        <div class="p-6 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-semibold text-lg text-slate-800">Pengajuan Terbaru</h2>
            <a href="pengajuan-sparepart.php" class="text-sm font-medium text-blue-500 hover:text-blue-600">
                Lihat semua
            </a>
        </div>

        <?php if (mysqli_num_rows($recentRows) === 0): ?>
            <div class="p-12 text-center">
                <i class="ti ti-clipboard-off text-4xl text-slate-300"></i>
                <p class="text-slate-400 mt-3 text-sm">Belum ada pengajuan sparepart.</p>
                <a href="pengajuan-sparepart/create-pengajuan.php"
                    class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-blue-500 text-white text-sm font-medium hover:bg-blue-600 transition-colors">
                    <i class="ti ti-plus"></i> Buat Pengajuan Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Tanggal</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Sparepart</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide text-center">Qty</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recentRows)):
                            $kode = !empty($row['kode_komponen'])
                                ? htmlspecialchars($row['kode_komponen']) . '-' . htmlspecialchars($row['kode_sparepart'])
                                : htmlspecialchars($row['kode_sparepart']);

                            $statusMap = [
                                'draft'     => ['label' => 'Draft',    'color' => 'bg-slate-100 text-slate-600',  'dot' => 'bg-slate-400'],
                                'menunggu'  => ['label' => 'Menunggu', 'color' => 'bg-yellow-50 text-yellow-600', 'dot' => 'bg-yellow-500'],
                                'disetujui' => ['label' => 'Disetujui','color' => 'bg-green-50 text-green-600',   'dot' => 'bg-green-500'],
                                'ditolak'   => ['label' => 'Ditolak',  'color' => 'bg-red-50 text-red-600',       'dot' => 'bg-red-500'],
                            ];
                            $s = $statusMap[$row['status']] ?? ['label' => ucfirst($row['status']), 'color' => 'bg-slate-100 text-slate-600', 'dot' => 'bg-slate-400'];
                        ?>
                        <tr class="border-t border-slate-200 hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm text-slate-500">
                                <?= date('d M Y', strtotime($row['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($row['nama_sparepart']) ?></p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5"><?= $kode ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 text-center">
                                <?= (int)$row['quantity'] ?> pcs
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $s['color'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                                    <?= $s['label'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Riwayat Maintenance Terbaru -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mt-6">

        <div class="p-6 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-semibold text-lg text-slate-800">Maintenance Terbaru</h2>
            <a href="maintenance.php" class="text-sm font-medium text-orange-500 hover:text-orange-600">
                Lihat semua
            </a>
        </div>

        <?php if (empty($maintData)): ?>
            <div class="p-12 text-center">
                <i class="ti ti-tool text-4xl text-slate-300"></i>
                <p class="text-slate-400 mt-3 text-sm">Belum ada riwayat maintenance.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Tanggal</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Mekanik</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Sparepart Dipakai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintData as $m): ?>
                        <tr class="border-t border-slate-200 hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm text-slate-500 whitespace-nowrap">
                                <?= date('d M Y', strtotime($m['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($m['type_unit']) ?></p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($m['nopol']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <?= htmlspecialchars($m['mekanik']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($m['sparepart_list_decoded'] as $item):
                                        $nama = $sparepartNames[(int)$item['sparepart_id']] ?? 'Sparepart #' . $item['sparepart_id'];
                                    ?>
                                        <span class="inline-flex items-center gap-1 text-xs bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full">
                                            <?= htmlspecialchars($nama) ?>
                                            <span class="text-orange-400">×<?= (int)$item['quantity'] ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include '../layouts/footer.php'; ?>