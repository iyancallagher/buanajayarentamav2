<?php
require_once '../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// ===== 1. Ambil Filter Bulan & Tahun (Default: Bulan & Tahun Ini) =====
$selectedMonth = $_GET['bulan'] ?? date('m');
$selectedYear  = $_GET['tahun'] ?? date('Y');
$searchValue   = $_GET['search'] ?? '';

// ===== 2. Setup Pagination =====
$perPage     = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ===== 3. Hitung Total Baris untuk Pagination =====
$countSql = "
    SELECT COUNT(*) as total 
    FROM maintenance_wk m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE MONTH(m.created_at) = ? AND YEAR(m.created_at) = ?
";
$countParams = [$selectedMonth, $selectedYear];
$countTypes  = 'ss';

if (!empty($searchValue)) {
    $countSql .= " AND (m.type_unit LIKE ? OR m.nopol LIKE ? OR m.mekanik LIKE ? OR u.username LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countParams[] = $likeValue;
    $countTypes   .= 'ssss';
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
mysqli_stmt_execute($countStmt);
$totalRows = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
$totalPages = ceil($totalRows / $perPage);

// ===== 4. Kueri Utama Data Maintenance WK =====
$sql = "
    SELECT m.*, u.nama AS nama_workshop 
    FROM maintenance_wk m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE MONTH(m.created_at) = ? AND YEAR(m.created_at) = ?
";
$params = [$selectedMonth, $selectedYear];
$types  = 'ss';

if (!empty($searchValue)) {
    $sql .= " AND (m.type_unit LIKE ? OR m.nopol LIKE ? OR m.mekanik LIKE ? OR u.username LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $params[] = $likeValue;
    $params[] = $likeValue;
    $params[] = $likeValue;
    $params[] = $likeValue;
    $types   .= 'ssss';
}

$sql .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ===== 5. Ambil Data Master Sparepart untuk Mapping ID -> Nama =====
$sparepartMaster = [];
$spQuery = mysqli_query($conn, "SELECT id, nama_sparepart FROM sparepart"); 
if ($spQuery) {
    while ($spRow = mysqli_fetch_assoc($spQuery)) {
        $sparepartMaster[$spRow['id']] = $spRow['nama_sparepart'];
    }
}

// ===== 6. Siapkan Konfigurasi Kolom untuk Base Template Table =====
$columns = [
    ['key' => 'no', 'label' => 'No.', 'align' => 'left'],
    ['key' => 'tanggal', 'label' => 'Tanggal Masuk', 'align' => 'left', 'raw' => true],
    ['key' => 'workshop', 'label' => 'User / Workshop', 'align' => 'left', 'raw' => true], 
    ['key' => 'unit', 'label' => 'Tipe Unit / Nopol', 'align' => 'left', 'raw' => true],
    ['key' => 'mekanik', 'label' => 'Mekanik', 'align' => 'left', 'raw' => true],
    ['key' => 'total_part', 'label' => 'Jumlah Suku Cadang', 'align' => 'left', 'raw' => true],
    ['key' => 'aksi', 'label' => 'Aksi', 'align' => 'center', 'raw' => true]
];

// ===== 7. Mapping Data Query MySQL ke dalam Array Rows =====
$rows = [];
$no = $offset + 1;

while ($row = mysqli_fetch_assoc($result)) {
    $spareparts = json_decode($row['sparepart_list'], true) ?: [];
    
    $sparepartWithNames = [];
    foreach ($spareparts as $item) {
        $spId = $item['sparepart_id'] ?? null;
        $qty  = $item['quantity'] ?? $item['qty'] ?? 1;
        $namaPart = $sparepartMaster[$spId] ?? "Sparepart ID: " . $spId;
        
        $sparepartWithNames[] = [
            'nama' => $namaPart,
            'qty'  => $qty
        ];
    }
    
    $countSparepart = count($sparepartWithNames);
    
    $htmlTanggal = "
        <p class='font-semibold text-slate-700'>" . date('d M Y', strtotime($row['created_at'])) . "</p>
        <span class='text-[10px] text-slate-400'>" . date('H:i', strtotime($row['created_at'])) . " WITA</span>
    ";

    $namaWorkshop = $row['nama_workshop'] ? htmlspecialchars($row['nama_workshop']) : 'ID: ' . $row['user_id'];
    $htmlWorkshop = "
        <div class='flex items-center gap-1.5 text-slate-700 font-bold'>
            <i class='ti ti-building-fortress text-blue-500 text-sm'></i>
            <span>" . $namaWorkshop . "</span>
        </div>
    ";
    
    $htmlUnit = "
        <p class='font-bold text-slate-800'>" . htmlspecialchars($row['type_unit']) . "</p>
        <span class='text-[11px] text-blue-600 font-mono mt-0.5 bg-blue-50 px-1.5 py-0.5 rounded inline-block font-semibold'>" . htmlspecialchars($row['nopol']) . "</span>
    ";
    
    $htmlMekanik = "
        <div class='flex items-center gap-1.5 text-slate-700 font-medium'>
            <i class='ti ti-user-mechanic text-slate-400 text-sm'></i>
            <span>" . htmlspecialchars($row['mekanik']) . "</span>
        </div>
    ";
    
    $htmlTotalPart = "
        <span class='bg-slate-100 text-slate-700 px-2 py-1 rounded-md font-bold text-[11px]'>
            " . $countSparepart . " Jenis Part
        </span>
    ";
    
    $jsonEscapedData = htmlspecialchars(json_encode($sparepartWithNames), ENT_QUOTES, 'UTF-8');
    
    $htmlAksi = "
        <div class='flex items-center justify-center gap-1.5'>
            <button type='button'
                @click=\"selectedMaintenance = {
                    unit: '" . htmlspecialchars($row['type_unit'], ENT_QUOTES) . "',
                    nopol: '" . htmlspecialchars($row['nopol'], ENT_QUOTES) . "',
                    tanggal: '" . date('d M Y H:i', strtotime($row['created_at'])) . " WITA',
                    mekanik: '" . htmlspecialchars($row['mekanik'], ENT_QUOTES) . "',
                    workshop: '" . $namaWorkshop . "',
                    sparepart: " . $jsonEscapedData . "
                }; openDetail = true\"
                class='p-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg transition-colors inline-flex items-center gap-1.5 text-[11px]' title='Lihat Detail'>
                <i class='ti ti-eye text-sm'></i>
            </button>
            
            <a href='workshop-maintenance/edit-maintenance.php?id=" . $row['id'] . "' 
               class='p-2 bg-white hover:bg-slate-50 text-blue-600 border border-slate-200 font-semibold rounded-lg transition-colors inline-flex items-center shadow-sm' title='Edit Laporan'>
                <i class='ti ti-pencil text-sm'></i>
            </a>
        </div>
    ";

    $rows[] = [
        'no'         => $no++,
        'tanggal'    => $htmlTanggal,
        'workshop'   => $htmlWorkshop,
        'unit'       => $htmlUnit,
        'mekanik'    => $htmlMekanik,
        'total_part' => $htmlTotalPart,
        'aksi'       => $htmlAksi
    ];
}

// Array daftar nama bulan
$namaBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$tahunSekarang = (int)date('Y');
$pilihanTahun = range($tahunSekarang, $tahunSekarang - 3);

$emptyMessage = 'Tidak ada riwayat perbaikan terdaftar pada periode ini.';
$showSearch = false; 

if (!empty($_SESSION['form_success'])): $formSuccess = $_SESSION['form_success']; unset($_SESSION['form_success']); endif;
if (!empty($_SESSION['form_error'])): $formError = $_SESSION['form_error']; unset($_SESSION['form_error']); endif;
?>

<main class="p-6 bg-slate-50 min-h-screen" x-data="{ openDetail: false, selectedMaintenance: { sparepart: [], workshop: '' } }">
    
    <div class="mb-6">
        <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Manajer Panel</p>
        <h1 class="text-2xl font-bold text-slate-800">Laporan Workshop Maintenance</h1>
        <p class="text-slate-500 mt-1">Daftar rekapan perbaikan unit dan pergantian suku cadang oleh mekanik.</p>
    </div>

    <?php if (!empty($formSuccess)): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2 shadow-sm">
        <i class="ti ti-circle-check text-base shrink-0 text-green-500"></i>
        <?= htmlspecialchars($formSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($formError)): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2 shadow-sm">
        <i class="ti ti-alert-circle text-base shrink-0 text-red-500"></i>
        <?= htmlspecialchars($formError) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" id="filterPeriodeForm" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="w-40">
                <select name="bulan" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <?php foreach ($namaBulan as $mKey => $mName): ?>
                        <option value="<?= $mKey ?>" <?= $selectedMonth === $mKey ? 'selected' : '' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-28">
                <select name="tahun" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <?php foreach ($pilihanTahun as $tVal): ?>
                        <option value="<?= $tVal ?>" <?= $selectedYear == $tVal ? 'selected' : '' ?>><?= $tVal ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" onclick="exportMaintenanceExcel()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm hover:-translate-y-0.5 transition-all duration-200 whitespace-nowrap">
                <i class="ti ti-file-spreadsheet mr-1"></i> Export Excel
            </button>

            <?php if (!empty($searchValue)): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchValue) ?>" id="excelSearchHidden">
            <?php endif; ?>
        </form>

        <form method="GET" class="relative w-full md:w-72">
            <input type="hidden" name="bulan" value="<?= htmlspecialchars($selectedMonth) ?>">
            <input type="hidden" name="tahun" value="<?= htmlspecialchars($selectedYear) ?>">
            <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($searchValue) ?>" placeholder="Cari nopol, unit, user..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 bg-slate-50">
        </form>
    </div>

    <?php 
    $tableTitle = "Rekap Riwayat Pekerjaan Workshop";
    include '../components/table.php'; 
    ?>

    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-5 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs text-slate-500">Menampilkan halaman <span class="font-bold text-slate-800"><?= $currentPage ?></span> dari <span class="font-bold text-slate-800"><?= $totalPages ?></span> halaman.</p>
        <div class="flex gap-1.5">
            <a href="?bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>&search=<?= urlencode($searchValue) ?>&page=<?= max(1, $currentPage - 1) ?>" class="px-3 py-1.5 rounded-lg border text-xs font-semibold <?= $currentPage === 1 ? 'pointer-events-none text-slate-300 bg-slate-50 border-slate-100' : 'text-slate-600 bg-white hover:bg-slate-50 border-slate-200' ?>"><i class="ti ti-chevron-left"></i> Prev</a>
            <a href="?bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>&search=<?= urlencode($searchValue) ?>&page=<?= min($totalPages, $currentPage + 1) ?>" class="px-3 py-1.5 rounded-lg border text-xs font-semibold <?= $currentPage === $totalPages ? 'pointer-events-none text-slate-300 bg-slate-50 border-slate-100' : 'text-slate-600 bg-white hover:bg-slate-50 border-slate-200' ?>">Next <i class="ti ti-chevron-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <div x-show="openDetail" x-cloak class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4" x-transition>
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden" @click.away="openDetail = false">
            <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                <h4 class="font-bold text-sm flex items-center gap-1.5"><i class="ti ti-settings-automation text-base text-blue-400"></i> Detail Penggantian Sparepart</h4>
                <button type="button" @click="openDetail = false" class="text-slate-400 hover:text-white"><i class="ti ti-x text-base"></i></button>
            </div>
            
            <div class="p-6 space-y-4 text-xs">
                <div class="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-slate-400 font-medium block">Tipe Unit / Nopol</span>
                            <p class="text-slate-800 font-bold" x-text="selectedMaintenance.unit + ' ('+ selectedMaintenance.nopol +')'"></p>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium block">Mekanik</span>
                            <p class="text-slate-800 font-semibold" x-text="selectedMaintenance.mekanik"></p>
                        </div>
                    </div>
                    <div class="border-t border-slate-200/60 pt-2 mt-1">
                        <span class="text-slate-400 font-medium block">User / Workshop Pelapor</span>
                        <p class="text-blue-600 font-bold" x-text="selectedMaintenance.workshop"></p>
                    </div>
                </div>

                <div>
                    <span class="text-slate-500 font-bold block mb-2 uppercase tracking-wide">Daftar Suku Cadang Terpasang:</span>
                    <div class="border border-slate-200 rounded-xl overflow-hidden max-h-60 overflow-y-auto">
                        <table class="w-full text-left">
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, index) in selectedMaintenance.sparepart" :key="index">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-2.5 px-3 font-medium text-slate-800" x-text="item.nama"></td>
                                        <td class="py-2.5 px-3 text-center font-bold text-slate-600 w-20" x-text="item.qty + ' pcs'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 text-right italic" x-text="'Direkam pada: ' + selectedMaintenance.tanggal"></div>
                <button type="button" @click="openDetail = false" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition-colors">Tutup Detail</button>
            </div>
        </div>
    </div>
</main>

<script>
function exportMaintenanceExcel() {
    const form = document.getElementById('filterPeriodeForm');
    const originalAction = form.action;
    
    // Cari input teks pencarian dari sebelah kanan
    const searchInput = document.querySelector('input[name="search"]:not([type="hidden"])');
    let hiddenSearch = document.getElementById('excelSearchHidden');
    
    if (searchInput && searchInput.value.trim() !== "") {
        if (!hiddenSearch) {
            hiddenSearch = document.createElement('input');
            hiddenSearch.type = 'hidden';
            hiddenSearch.name = 'search';
            hiddenSearch.id = 'excelSearchHidden';
            form.appendChild(hiddenSearch);
        }
        hiddenSearch.value = searchInput.value;
    } else {
        if (hiddenSearch) {
            hiddenSearch.value = "";
        }
    }

    form.action = 'laporan/export-excel-maintenance.php';
    form.submit();
    
    form.action = originalAction;
}
</script>

<?php include '../layouts/footer.php'; ?>