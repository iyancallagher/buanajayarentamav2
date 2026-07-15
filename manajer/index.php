<?php

require_once '../auth/auth_check.php';
requireRole(['manajer operasional']); // Hanya manajer operasional yang boleh akses halaman ini

require_once '../config/database.php';
require_once '../components/notif-pengajuan.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// =========================================================================
// 0. NOTIFIKASI: PENGAJUAN MENUNGGU VERIFIKASI
// =========================================================================
$pengajuanMenunggu = getPengajuanMenunggu($conn);

// =========================================================================
// 1. STATISTIK UTAMA (Dinamis Berdasarkan Data Bulan Ini)
// =========================================================================

// Total Sesi Perbaikan bulan ini di maintenance_wk
$totalRequest = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM maintenance_wk 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
"))['total'] ?? 0;

// Total Item Suku Cadang Terdaftar
$totalSparepart = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM sparepart
"))['total'] ?? 0;

// Total Volume Suku Cadang Masuk (Warehouse) bulan ini
$totalMasukCount = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(quantity) as total FROM sparepart_masuk_wr
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
"))['total'] ?? 0;

// Total Volume Suku Cadang Keluar (Warehouse) bulan ini
$totalKeluarCount = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(quantity) as total FROM sparepart_keluar_wr
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
"))['total'] ?? 0;


// =========================================================================
// 2. HELPER FORMAT NAMA GABUNGAN
// =========================================================================
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


// =========================================================================
// 3. DATA UNTUK DIAGRAM BALOK (Tren 6 Bulan Terakhir)
// =========================================================================
$monthsLabel = [];
$chartMasukData = [];
$chartKeluarData = [];

for ($i = 5; $i >= 0; $i--) {
    $monthsLabel[] = date('M Y', strtotime("-$i months"));
    $targetMonth = date('m', strtotime("-$i months"));
    $targetYear  = date('Y', strtotime("-$i months"));

    // Masuk per bulan
    $mQ = mysqli_query($conn, "SELECT SUM(quantity) as total FROM sparepart_masuk_wr WHERE MONTH(created_at) = '$targetMonth' AND YEAR(created_at) = '$targetYear'");
    $chartMasukData[] = (int)(mysqli_fetch_assoc($mQ)['total'] ?? 0);

    // Keluar per bulan
    $kQ = mysqli_query($conn, "SELECT SUM(quantity) as total FROM sparepart_keluar_wr WHERE MONTH(created_at) = '$targetMonth' AND YEAR(created_at) = '$targetYear'");
    $chartKeluarData[] = (int)(mysqli_fetch_assoc($kQ)['total'] ?? 0);
}


// =========================================================================
// 4. MINI TABLE: 5 SPAREPART MASUK TERBARU
// =========================================================================
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
    $row['kode_gabungan'] = !empty($row['kode_komponen']) ? $row['kode_komponen'] . '-' . $row['kode_sparepart'] : $row['kode_sparepart'];
    $sparepartMasukList[] = $row;
}


// =========================================================================
// 5. MINI TABLE: 5 SPAREPART KELUAR TERBARU
// =========================================================================
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
    $row['kode_gabungan'] = !empty($row['kode_komponen']) ? $row['kode_komponen'] . '-' . $row['kode_sparepart'] : $row['kode_sparepart'];
    $sparepartKeluarList[] = $row;
}

?>

<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
            Manajer Panel
        </p>
        <h1 class="text-2xl font-bold text-slate-800">
            Dashboard
        </h1>
        <p class="text-slate-500 mt-1">
            Ringkasan data operasional sparepart di warehouse dan workshop.
        </p>
    </div>

    <!-- Banner notifikasi: pengajuan menunggu verifikasi -->
    <?php if ($pengajuanMenunggu > 0): ?>
    <a href="pengajuan-sparepart.php"
        class="group flex items-center gap-4 mb-6 p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 hover:bg-amber-100/70 transition-colors">

        <div class="relative w-11 h-11 rounded-xl bg-amber-500 flex items-center justify-center shrink-0">
            <i class="ti ti-bell-ringing text-xl text-white"></i>
            <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-red-500 ring-2 ring-amber-50 animate-pulse"></span>
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-900">
                <?= $pengajuanMenunggu ?> pengajuan sparepart menunggu verifikasi
            </p>
            <p class="text-xs text-amber-700 mt-0.5">
                Workshop sedang menunggu keputusan kamu. Klik untuk meninjau sekarang.
            </p>
        </div>

        <i class="ti ti-arrow-right text-lg text-amber-600 shrink-0 group-hover:translate-x-1 transition-transform"></i>
    </a>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

        <div class="bg-blue-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-blue-100 font-medium">Maintenance</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= number_format($totalRequest, 0, ',', '.') ?></h3>
                    <p class="text-xs text-blue-100/80 mt-2"> bulan ini</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-tools text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-indigo-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-indigo-100 font-medium">Katalog Sparepart</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= number_format($totalSparepart, 0, ',', '.') ?></h3>
                    <p class="text-xs text-indigo-100/80 mt-2"> item terdaftar</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-package text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-emerald-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-emerald-100 font-medium">Sparepart Masuk Warehouse</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= number_format($totalMasukCount, 0, ',', '.') ?></h3>
                    <p class="text-xs text-emerald-100/80 mt-2">Volume masuk bulan ini</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-arrow-down-left text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-rose-500 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-rose-100 font-medium">Sparepart Keluar Warehouse</p>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?= number_format($totalKeluarCount, 0, ',', '.') ?></h3>
                    <p class="text-xs text-rose-100/80 mt-2">Volume keluar bulan ini</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <i class="ti ti-arrow-up-right text-2xl text-white"></i>
                </div>
            </div>
        </div>

    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="font-semibold text-slate-800 text-base">Arus Suku Cadang Warehouse</h2>
                <p class="text-xs text-slate-400">Rasio perbandingan volume barang masuk dan keluar (6 bulan terakhir).</p>
            </div>
            <div class="flex gap-4 text-xs font-medium">
                <span class="flex items-center gap-1.5 text-slate-600">
                    <span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span> Masuk
                </span>
                <span class="flex items-center gap-1.5 text-slate-600">
                    <span class="w-3 h-3 rounded bg-rose-500 inline-block"></span> Keluar
                </span>
            </div>
        </div>
        <div class="relative w-full h-72">
            <canvas id="operasionalBarChart"></canvas>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <i class="ti ti-arrow-down-circle text-lg text-emerald-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Sparepart Masuk Terbaru</h2>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase bg-white border px-2 py-0.5 rounded-md shadow-3xs">Inflow</span>
            </div>

            <?php if (empty($sparepartMasukList)): ?>
            <div class="p-6 text-center text-sm text-slate-400">
                Belum ada data suku cadang masuk.
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($sparepartMasukList as $item): ?>
                <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/30 transition-colors">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 leading-relaxed truncate">
                            <?= htmlspecialchars($item['nama_lengkap']) ?>
                        </p>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            <?= htmlspecialchars($item['kode_gabungan']) ?>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= date('d M Y, H:i', strtotime($item['created_at'])) ?>
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-emerald-600 shrink-0">
                        +<?= number_format($item['quantity'], 0, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <i class="ti ti-arrow-up-circle text-lg text-rose-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Sparepart Keluar Terbaru</h2>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase bg-white border px-2 py-0.5 rounded-md shadow-3xs">Outflow</span>
            </div>

            <?php if (empty($sparepartKeluarList)): ?>
            <div class="p-6 text-center text-sm text-slate-400">
                Belum ada data suku cadang keluar.
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($sparepartKeluarList as $item): ?>
                <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/30 transition-colors">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 leading-relaxed truncate">
                            <?= htmlspecialchars($item['nama_lengkap']) ?>
                        </p>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            <?= htmlspecialchars($item['kode_gabungan']) ?>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= $item['nama_penerima'] ? 'Ke ' . htmlspecialchars($item['nama_penerima']) . ' · ' : '' ?><?= date('d M Y, H:i', strtotime($item['created_at'])) ?>
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-rose-600 shrink-0">
                        -<?= number_format($item['quantity'], 0, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<script>
    const ctx = document.getElementById('operasionalBarChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthsLabel) ?>,
            datasets: [
                {
                    label: 'Masuk',
                    data: <?= json_encode($chartMasukData) ?>,
                    backgroundColor: '#10b981', // Emerald 500
                    borderRadius: 6,
                    barPercentage: 0.55,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Keluar',
                    data: <?= json_encode($chartKeluarData) ?>,
                    backgroundColor: '#f43f5e', // Rose 500
                    borderRadius: 6,
                    barPercentage: 0.55,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { color: '#94a3b8', font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 11, weight: '600' } }
                }
            }
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>