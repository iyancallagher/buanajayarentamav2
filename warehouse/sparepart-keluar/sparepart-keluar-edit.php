<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['form_error'] = "ID Transaksi tidak valid.";
    header("Location: ../sparepart-keluar.php");
    exit;
}

// Ambil data detail pengeluaran tanpa join ke stok gudang karena tidak dibutuhkan
$sql = "SELECT k.id as keluar_id, k.quantity as qty_keluar, k.sparepart_id,
               s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit, kp.kode_komponen
        FROM sparepart_keluar_wr k
        JOIN sparepart s ON s.id = k.sparepart_id
        LEFT JOIN komponen kp ON kp.id = s.komponen_id
        WHERE k.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['form_error'] = "Data riwayat transaksi pengeluaran tidak ditemukan.";
    header("Location: ../sparepart-keluar.php");
    exit;
}

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
        <a href="../sparepart-keluar.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Sparepart Keluar</h1>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
        
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">Koreksi Kuantitas Keluar</h2>
                <p class="text-sm text-slate-500 mt-1">Ubah kuantitas item pada riwayat pengeluaran ini secara langsung.</p>
            </div>

            <form action="sparepart-keluar-store.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="keluar_id" value="<?= $data['keluar_id'] ?>">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Jumlah Keluar Baru</label>
                    <div class="relative">
                        <i class="ti ti-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            type="number"
                            name="quantity"
                            value="<?= $data['qty_keluar'] ?>"
                            min="1"
                            required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i> Simpan Kuantitas
                    </button>
                    <a href="../sparepart-keluar.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
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
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Nama Lengkap & Spesifikasi</span>
                        <span class="font-medium text-slate-800 leading-relaxed block mt-0.5"><?= $namaLengkap ?></span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border p-5 shadow-sm bg-blue-50 border-blue-100">
                <div class="flex items-start gap-3">
                    <i class="ti ti-info-circle text-xl text-blue-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h4 class="font-semibold text-blue-700 text-sm mb-1">Perubahan Riwayat Saja</h4>
                        <p class="text-xs text-blue-600 leading-relaxed">
                            Fitur ini hanya mengubah catatan angka kuantitas pada log riwayat pengeluaran warehouse.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<?php include '../../layouts/footer.php'; ?>