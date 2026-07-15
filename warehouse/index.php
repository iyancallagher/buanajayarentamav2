<?php

require_once '../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../config/database.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// ===== STATISTIK =====

$totalSparepart = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM sparepart"))['total'];

$stokMenipis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total
    FROM stok_sparepart_wr
    WHERE stok > 0 AND stok <= minimal_stok
"))['total'];

$stokHabis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total
    FROM stok_sparepart_wr
    WHERE stok <= 0
"))['total'];

$pengajuanSiapCetak = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total
    FROM pengajuan_sparepart
    WHERE status = 'setuju' AND surat_jalan_id IS NULL
"))['total'];

// Helper untuk format nama sparepart gabungan, dipakai di kedua mini table
function formatNamaSparepart(string $nama, ?string $typeUnit, ?string $numberPart): string
{
    $typeUnitArray   = json_decode($typeUnit ?? '[]', true) ?: [];
    $numberPartArray = json_decode($numberPart ?? '[]', true) ?: [];

    $typeUnitText   = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = $nama;
    if (!empty($typeUnitText))   $namaLengkap .= ' / ' . $typeUnitText;
    if (!empty($numberPartText)) $namaLengkap .= '/ ' . $numberPartText;

    return $namaLengkap;
}

// ===== MINI TABLE: 5 Sparepart Masuk Terbaru =====
$sparepartMasukResult = mysqli_query($conn, "
    SELECT m.quantity, m.created_at,
           s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit,
           k.kode_komponen
    FROM sparepart_masuk_wr m
    JOIN sparepart s ON s.id = m.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    ORDER BY m.created_at DESC
    LIMIT 5
");

$sparepartMasukList = [];
while ($row = mysqli_fetch_assoc($sparepartMasukResult)) {

    $row['nama_lengkap'] = formatNamaSparepart($row['nama_sparepart'], $row['type_unit'], $row['number_part']);

    $row['kode_gabungan'] = !empty($row['kode_komponen'])
        ? $row['kode_komponen'] . '-' . $row['kode_sparepart']
        : $row['kode_sparepart'];

    $sparepartMasukList[] = $row;
}

// ===== MINI TABLE: 5 Sparepart Keluar Terbaru =====
$sparepartKeluarResult = mysqli_query($conn, "
    SELECT k.quantity, k.created_at,
           s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit,
           kp.kode_komponen,
           u.nama as nama_penerima
    FROM sparepart_keluar_wr k
    JOIN sparepart s ON s.id = k.sparepart_id
    LEFT JOIN komponen kp ON kp.id = s.komponen_id
    LEFT JOIN users u ON u.id = k.user_id
    ORDER BY k.created_at DESC
    LIMIT 5
");

$sparepartKeluarList = [];
while ($row = mysqli_fetch_assoc($sparepartKeluarResult)) {

    $row['nama_lengkap'] = formatNamaSparepart($row['nama_sparepart'], $row['type_unit'], $row['number_part']);

    $row['kode_gabungan'] = !empty($row['kode_komponen'])
        ? $row['kode_komponen'] . '-' . $row['kode_sparepart']
        : $row['kode_sparepart'];

    $sparepartKeluarList[] = $row;
}

?>
<main class="p-6 bg-slate-50 min-h-screen">

    <!-- Page Header -->
    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
            Warehouse Panel
        </p>
        <h1 class="text-2xl font-bold text-slate-800">
            Dashboard
        </h1>
        <p class="text-slate-500 mt-1">
            Ringkasan aktivitas dan kondisi stok warehouse.
        </p>
    </div>

    <!-- Banner notifikasi: pengajuan siap dicetak -->
    <?php if ($pengajuanSiapCetak > 0): ?>
    <a href="daftar-sparepart.php"
        class="group flex items-center gap-4 mb-6 p-4 sm:p-5 rounded-2xl bg-green-50 border border-green-200 hover:bg-green-100/70 transition-colors">

        <div class="relative w-11 h-11 rounded-xl bg-green-500 flex items-center justify-center shrink-0">
            <i class="ti ti-clipboard-check text-xl text-white"></i>
            <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-red-500 ring-2 ring-green-50 animate-pulse"></span>
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-green-900">
                <?= $pengajuanSiapCetak ?> pengajuan sparepart menunggu konfirmasi pengiriman
            </p>
            <p class="text-xs text-green-700 mt-0.5">
                Pengajuan sudah disetujui manajer dan menunggu surat jalan. Klik untuk memproses sekarang.
            </p>
        </div>

        <i class="ti ti-arrow-right text-lg text-green-600 shrink-0 group-hover:translate-x-1 transition-transform"></i>
    </a>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

        <div class="bg-blue-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-blue-100 font-medium">
                        Total Sparepart
                    </p>
                    <h3 class="text-3xl font-bold mt-2 text-white">
                        <?= number_format($totalSparepart, 0, ',', '.') ?>
                    </h3>
                    <p class="text-xs text-blue-100/80 mt-2">
                        Terdaftar di master data
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-package text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-yellow-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-yellow-100 font-medium">
                        Stok Menipis
                    </p>
                    <h3 class="text-3xl font-bold mt-2 text-white">
                        <?= number_format($stokMenipis, 0, ',', '.') ?>
                    </h3>
                    <p class="text-xs text-yellow-100/80 mt-2">
                        Di bawah batas minimum
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-alert-triangle text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-red-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-red-100 font-medium">
                        Stok Habis
                    </p>
                    <h3 class="text-3xl font-bold mt-2 text-white">
                        <?= number_format($stokHabis, 0, ',', '.') ?>
                    </h3>
                    <p class="text-xs text-red-100/80 mt-2">
                        Perlu segera direstock
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-circle-x text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-green-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-green-100 font-medium">
                        Siap Dicetak
                    </p>
                    <h3 class="text-3xl font-bold mt-2 text-white">
                        <?= number_format($pengajuanSiapCetak, 0, ',', '.') ?>
                    </h3>
                    <p class="text-xs text-green-100/80 mt-2">
                        Pengajuan disetujui
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-clipboard-check text-2xl text-white"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Quick Menu -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

        <a href="sparepart-masuk/create-sparepart-masuk.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-200 hover:-translate-y-0.5 transition-all duration-200">

            <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-500 transition-colors duration-200">
                <i class="ti ti-truck-loading text-2xl text-blue-500 group-hover:text-white transition-colors duration-200"></i>
            </div>

            <h3 class="font-semibold text-lg mt-4 text-slate-800">
                Sparepart Masuk
            </h3>

            <p class="text-sm text-slate-500 mt-2">
                Catat sparepart yang baru diterima warehouse.
            </p>

            <div class="flex items-center gap-1 text-sm font-medium text-blue-500 mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>

        </a>

        <a href="daftar-sparepart.php"
            class="group relative bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-orange-200 hover:-translate-y-0.5 transition-all duration-200">

            <?php if ($pengajuanSiapCetak > 0): ?>
            <span class="absolute top-4 right-4 inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold leading-none">
                <?= $pengajuanSiapCetak > 99 ? '99+' : $pengajuanSiapCetak ?>
            </span>
            <?php endif; ?>

            <div class="w-14 h-14 rounded-xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-500 transition-colors duration-200">
                <i class="ti ti-clipboard-list text-2xl text-orange-500 group-hover:text-white transition-colors duration-200"></i>
            </div>

            <h3 class="font-semibold text-lg mt-4 text-slate-800">
                Pengajuan Sparepart
            </h3>

            <p class="text-sm text-slate-500 mt-2">
                Lihat pengiriman dari pengajuan disetujui.
            </p>

            <div class="flex items-center gap-1 text-sm font-medium text-orange-500 mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>

        </a>

        <a href="sparepart-stok.php"
            class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-green-200 hover:-translate-y-0.5 transition-all duration-200">

            <div class="w-14 h-14 rounded-xl bg-green-50 flex items-center justify-center group-hover:bg-green-500 transition-colors duration-200">
                <i class="ti ti-list-details text-2xl text-green-500 group-hover:text-white transition-colors duration-200"></i>
            </div>

            <h3 class="font-semibold text-lg mt-4 text-slate-800">
                Monitoring Stok
            </h3>   

            <p class="text-sm text-slate-500 mt-2">
                Pantau ketersediaan stok sparepart secara real-time.
            </p>

            <div class="flex items-center gap-1 text-sm font-medium text-green-500 mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                Buka <i class="ti ti-arrow-right"></i>
            </div>

        </a>

    </div>

    <!-- Mini Table: Sparepart Masuk & Keluar -->
    <div class="grid lg:grid-cols-2 gap-6">

        <!-- Mini Table: Sparepart Masuk -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="ti ti-arrow-down-circle text-lg text-green-500"></i>
                    <h2 class="font-semibold text-slate-800">Sparepart Masuk Terbaru</h2>
                </div>
                <a href="sparepart-masuk.php" class="text-xs font-medium text-blue-500 hover:text-blue-600">
                    Lihat semua
                </a>
            </div>

            <?php if (empty($sparepartMasukList)): ?>
            <div class="p-6 text-center text-sm text-slate-400">
                Belum ada data sparepart masuk.
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($sparepartMasukList as $item): ?>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 leading-relaxed">
                            <?= htmlspecialchars($item['nama_lengkap']) ?>
                        </p>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            <?= htmlspecialchars($item['kode_gabungan']) ?>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= date('d M Y, H:i', strtotime($item['created_at'])) ?>
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-green-600 shrink-0">
                        +<?= number_format($item['quantity'], 0, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- Mini Table: Sparepart Keluar -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="ti ti-arrow-up-circle text-lg text-red-500"></i>
                    <h2 class="font-semibold text-slate-800">Sparepart Keluar Terbaru</h2>
                </div>
                <a href="sparepart-keluar.php" class="text-xs font-medium text-blue-500 hover:text-blue-600">
                    Lihat semua
                </a>
            </div>

            <?php if (empty($sparepartKeluarList)): ?>
            <div class="p-6 text-center text-sm text-slate-400">
                Belum ada data sparepart keluar.
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($sparepartKeluarList as $item): ?>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 leading-relaxed">
                            <?= htmlspecialchars($item['nama_lengkap']) ?>
                        </p>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            <?= htmlspecialchars($item['kode_gabungan']) ?>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= $item['nama_penerima'] ? 'Ke ' . htmlspecialchars($item['nama_penerima']) . ' · ' : '' ?><?= date('d M Y, H:i', strtotime($item['created_at'])) ?>
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-red-500 shrink-0">
                        -<?= number_format($item['quantity'], 0, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

    </div>

</main>

<?php include '../layouts/footer.php'; ?>