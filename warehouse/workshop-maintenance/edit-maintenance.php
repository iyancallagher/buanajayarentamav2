<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Menangkap ID tiket perbaikan maintenance
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['form_error'] = "Parameter ID Maintenance tidak valid.";
    header("Location: ../workshop-maintenance.php"); // Sesuaikan nama file rekap utama Anda
    exit;
}

// 1. Ambil detail data transaksi maintenance
$sql = "SELECT m.*, u.nama AS nama_workshop 
        FROM maintenance_wk m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE m.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    $_SESSION['form_error'] = "Data riwayat pekerjaan tidak ditemukan.";
    header("Location: ../workshop-maintenance.php");
    exit;
}

// 2. Tarik master sparepart untuk keperluan label nama item
$sparepartMaster = [];
$spQuery = mysqli_query($conn, "SELECT id, nama_sparepart FROM sparepart");
while ($spRow = mysqli_fetch_assoc($spQuery)) {
    $sparepartMaster[$spRow['id']] = $spRow['nama_sparepart'];
}

// Decode list suku cadang terpasang
$sparepartsJson = json_decode($data['sparepart_list'], true) ?: [];

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>

<main class="p-6 bg-slate-50 min-h-screen">
    <div class="mb-6 flex items-center gap-3">
        <a href="../workshop-maintenance.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Koreksi Laporan Perbaikan</h1>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">Ubah Informasi & Kuantitas Pemakaian Suku Cadang</h2>
                <p class="text-sm text-slate-500 mt-1">Perbarui detail pengerjaan mekanik atau sesuaikan jumlah kuantitas log pemakaian unit.</p>
            </div>

            <form action="update-maintenance.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="maintenance_id" value="<?= $data['id'] ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Tipe Unit</label>
                        <input type="text" name="type_unit" value="<?= htmlspecialchars($data['type_unit']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Nomor Polisi (Nopol)</label>
                        <input type="text" name="nopol" value="<?= htmlspecialchars($data['nopol']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Nama Mekanik Pengerjaan</label>
                    <input type="text" name="mekanik" value="<?= htmlspecialchars($data['mekanik']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all">
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <span class="block text-sm font-semibold text-slate-700 mb-3 uppercase tracking-wider text-xs">Penyesuaian Kuantitas Suku Cadang</span>
                    
                    <div class="border border-slate-200 rounded-xl overflow-hidden bg-slate-50/50 divide-y divide-slate-200">
                        <?php foreach ($sparepartsJson as $index => $item): 
                            $spId = $item['sparepart_id'] ?? 0;
                            $qty  = $item['quantity'] ?? $item['qty'] ?? 1;
                            $namaItem = $sparepartMaster[$spId] ?? "Unknown Sparepart ID: " . $spId;
                        ?>
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
                                <div class="space-y-0.5">
                                    <span class="font-medium text-slate-800 text-sm"><?= htmlspecialchars($namaItem) ?></span>
                                    <p class="text-[11px] text-slate-400 font-mono">ID Referensi Barang: #<?= $spId ?></p>
                                    <input type="hidden" name="parts[<?= $index ?>][sparepart_id]" value="<?= $spId ?>">
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-medium text-slate-500">Qty Pakai:</label>
                                    <div class="relative w-28">
                                        <input type="number" name="parts[<?= $index ?>][qty]" value="<?= $qty ?>" min="1" required 
                                               class="w-full text-center pr-8 pl-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 pointer-events-none">Pcs</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i> Simpan Laporan
                    </button>
                    <a href="../workshop-maintenance.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">Batal</a>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-4 tracking-wide uppercase">Asal Dokumen</h3>
                <div class="space-y-3.5 text-sm">
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Workshop Pelapor</span>
                        <span class="font-bold text-blue-600 flex items-center gap-1.5 mt-1">
                            <i class="ti ti-building-factory-2"></i> <?= htmlspecialchars($data['nama_workshop'] ?? 'ID: '.$data['user_id']) ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Tanggal Input Awal</span>
                        <span class="font-medium text-slate-700 block mt-0.5"><?= date('d F Y H:i', strtotime($data['created_at'])) ?> WITA</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border p-5 shadow-sm bg-blue-50 border-blue-100">
                <div class="flex items-start gap-3">
                    <i class="ti ti-refresh text-xl text-blue-500 shrink-0 mt-0.5 animate-spin-slow"></i>
                    <div>
                        <h4 class="font-semibold text-sm text-blue-700 mb-1">Mekanisme Auto-Restock</h4>
                        <p class="text-xs text-blue-600 leading-relaxed">Jika kuantitas suku cadang diturunkan, sisa stok otomatis dikembalikan ke saldo gudang workshop terkait. Jika dinaikkan, stok gudang otomatis dikurangi.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../layouts/footer.php'; ?>