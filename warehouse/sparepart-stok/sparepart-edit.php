<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Ambil ID langsung dari parameter URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['form_error'] = "Parameter ID Sparepart tidak valid atau kosong.";
    header("Location: ../sparepart-stok.php");
    exit;
}

// Query mengambil data dari master sparepart (s) dan join ke stok warehouse (w)
$sql = "SELECT s.id, s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit, k.kode_komponen,
               IFNULL(w.id, 0) as stok_id, 
               IFNULL(w.stok, 0) as stok, 
               IFNULL(w.minimal_stok, 0) as minimal_stok 
        FROM sparepart s 
        LEFT JOIN komponen k ON k.id = s.komponen_id
        LEFT JOIN stok_sparepart_wr w ON s.id = w.sparepart_id 
        WHERE s.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

// Jika ID tidak ada di database master sparepart
if (!$data) {
    $_SESSION['form_error'] = "Data sparepart dengan ID " . $id . " tidak ditemukan di database.";
    header("Location: ../sparepart-stok.php");
    exit;
}

// Parsing data array JSON seperti biasa
$typeUnitArray   = json_decode($data['type_unit'] ?? '[]', true) ?: [];
$numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];
$typeUnitText   = implode(' / ', $typeUnitArray);
$numberPartText = implode('/', $numberPartArray);

$namaLengkap = htmlspecialchars($data['nama_sparepart']);
if (!empty($typeUnitText)) $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
if (!empty($numberPartText)) $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);

$kodeGabungan = !empty($data['kode_komponen'])
    ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode_sparepart'])
    : htmlspecialchars($data['kode_sparepart']);

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>

<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6 flex items-center gap-3">
        <a href="../sparepart-stok.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Parameter Stok</h1>
        </div>
    </div>

    <?php if (!empty($_SESSION['form_error'])): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
            <i class="ti ti-alert-circle text-base shrink-0"></i>
            <?= htmlspecialchars($_SESSION['form_error']) ?>
        </div>
        <?php unset($_SESSION['form_error']); endif; ?>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">Atur Parameter Stok</h2>
                <p class="text-sm text-slate-500 mt-1">Tentukan batas minimum peringatan dan koreksi jumlah stok fisik untuk item ini.</p>
            </div>

            <form action="sparepart-store.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="action" value="update_parameter">
                <input type="hidden" name="sparepart_id" value="<?= $data['id'] ?>">
                <input type="hidden" name="stok_id" value="<?= $data['stok_id'] ?>">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Batas Minimum Stok</label>
                    <div class="relative">
                        <i class="ti ti-alert-triangle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="number" name="minimal_stok" value="<?= $data['minimal_stok'] ?>" min="0" required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Sistem akan menandai item ini sebagai peringatan jika stok riil menyentuh atau berada di bawah nilai ini.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Jumlah Stok Aktual</label>
                    <div class="relative">
                        <i class="ti ti-box absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="number" name="stok" value="<?= $data['stok'] ?>" min="0" required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 transition-all">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Perubahan di sini akan langsung mengubah nilai stok secara mutlak tanpa mencatat log mutasi debet/kredit.</p>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i> Simpan Perubahan
                    </button>
                    <a href="../sparepart-stok.php"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                        Batal
                    </a>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-4 tracking-wide uppercase">Identitas Barang</h3>
                <div class="space-y-3.5 text-sm">
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Kode Sparepart</span>
                        <span class="font-mono text-slate-700 font-semibold bg-slate-100 px-2 py-0.5 rounded text-xs inline-block mt-1"><?= $kodeGabungan ?></span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Nama Lengkap</span>
                        <span class="font-medium text-slate-800 leading-relaxed block mt-0.5"><?= $namaLengkap ?></span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border p-5 shadow-sm bg-orange-50 border-orange-100">
                <div class="flex items-start gap-3">
                    <i class="ti ti-alert-triangle text-xl text-orange-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h4 class="font-semibold text-sm mb-1 text-orange-700">Perhatian</h4>
                        <p class="text-xs leading-relaxed text-orange-600">
                            Koreksi jumlah stok aktual bersifat langsung (overwrite) tanpa mencatat riwayat mutasi. Pastikan angka yang dimasukkan sudah sesuai kondisi fisik gudang.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../layouts/footer.php'; ?>