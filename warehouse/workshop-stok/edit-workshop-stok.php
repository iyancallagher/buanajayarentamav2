<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Menerima parameter ID langsung dari URL klik tombol edit
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['form_error'] = "Parameter ID Stok tidak valid atau kosong.";
    header("Location: ../workshop-stok.php");
    exit;
}

// Query mengambil data join berdasarkan PK id di tabel stok_sparepart_wk
$sql = "SELECT sw.id AS stok_id, sw.stok, s.nama_sparepart, s.kode_sparepart, k.kode_komponen, u.nama AS nama_workshop
        FROM stok_sparepart_wk sw
        JOIN sparepart s ON sw.sparepart_id = s.id
        JOIN users u ON sw.user_id = u.id
        LEFT JOIN komponen k ON k.id = s.komponen_id
        WHERE sw.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['form_error'] = "Data stok tidak ditemukan di database.";
    header("Location: ../workshop-stok.php");
    exit;
}

$kodeGabungan = !empty($data['kode_komponen'])
    ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode_sparepart'])
    : htmlspecialchars($data['kode_sparepart']);

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>

<main class="p-6 bg-slate-50 min-h-screen" x-data="{ editMode: 'koreksi' }">
    <div class="mb-6 flex items-center gap-3">
        <a href="../workshop-stok.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Stok Workshop</h1>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">Koreksi Kuantitas Fisik Stok</h2>
                <p class="text-sm text-slate-500 mt-1">Ubah nilai total persediaan fisik saat ini pada database workshop.</p>
            </div>

            <form action="update-workshop-stok.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="stok_id" value="<?= $data['stok_id'] ?>">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Jumlah Stok Aktual Baru</label>
                    <div class="relative">
                        <i class="ti ti-box absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="number" name="stok" value="<?= $data['stok'] ?>" min="0" required 
                               class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 transition-all">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-white font-semibold shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-adjustments text-base"></i> Terapkan Stok
                    </button>
                    <a href="../workshop-stok.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">Batal</a>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-4 tracking-wide uppercase">Identitas & Lokasi</h3>
                <div class="space-y-3.5 text-sm">
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Workshop Cabang</span>
                        <span class="font-bold text-blue-600 flex items-center gap-1.5 mt-1">
                            <i class="ti ti-building-factory-2"></i> <?= htmlspecialchars($data['nama_workshop']) ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Kode Sparepart</span>
                        <span class="font-mono text-slate-700 font-semibold bg-slate-100 px-2 py-0.5 rounded text-xs inline-block mt-1"><?= $kodeGabungan ?></span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Nama Item</span>
                        <span class="font-medium text-slate-800 block mt-0.5"><?= htmlspecialchars($data['nama_sparepart']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="rounded-2xl border p-5 shadow-sm bg-orange-50 border-orange-100">
                <div class="flex items-start gap-3">
                    <i class="ti ti-alert-triangle text-xl text-orange-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h4 class="font-semibold text-sm text-orange-700 mb-1">Panduan Parameter</h4>
                        <p class="text-xs text-orange-600 leading-relaxed">Peringatan: Perubahan angka stok di sini bertindak sebagai modifikasi nilai mutlak instan secara langsung tanpa mencatat log mutasi debet/kredit.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../layouts/footer.php'; ?>