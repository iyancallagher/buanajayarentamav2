<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['form_error'] = "ID pengiriman tidak valid.";
    header("Location: ../daftar-surat.php");
    exit;
}

// Ambil detail utama surat jalan
$sql = "SELECT sj.id, sj.nomor_surat, sj.status, u.nama as nama_user 
        FROM surat_jalan sj
        JOIN users u ON u.id = sj.user_id
        WHERE sj.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    $_SESSION['form_error'] = "Data pengiriman tidak ditemukan.";
    header("Location: ../daftar-surat.php");
    exit;
}

// Ambil item manifes untuk ditampilkan sebagai informasi (bukan untuk diedit input kuantitasnya)
$itemSql = "SELECT sjd.id as detail_id, sjd.quantity, sjd.quantity_diterima, s.nama_sparepart, s.kode_sparepart, s.type_unit, s.number_part, k.kode_komponen
            FROM surat_jalan_detail sjd
            JOIN sparepart s ON s.id = sjd.sparepart_id
            LEFT JOIN komponen k ON k.id = s.komponen_id
            WHERE sjd.surat_jalan_id = ?";
$itemStmt = mysqli_prepare($conn, $itemSql);
mysqli_stmt_bind_param($itemStmt, "i", $id);
mysqli_stmt_execute($itemStmt);
$itemsResult = mysqli_stmt_get_result($itemStmt);

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>

<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6 flex items-center gap-3">
        <a href="../daftar-surat.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Proses Kirim Susulan Sparepart</h1>
        </div>
    </div>

    <!-- Form diarahkan ke update-daftar-surat.php murni untuk mengubah status kembali ke dikirim -->
    <form action="update-daftar-surat.php" method="POST">
        <input type="hidden" name="surat_id" value="<?= $data['id'] ?>">

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-200">
                        <h2 class="font-semibold text-lg text-slate-800">Manifes Penerimaan Saat Ini</h2>
                        <p class="text-sm text-slate-500 mt-1">Gudang akan memproses pengiriman sisa kekurangan fisik barang sesuai catatan verifikasi workshop.</p>
                    </div>
                    
                    <div class="divide-y divide-slate-100 px-6 py-2">
                        <?php while ($item = mysqli_fetch_assoc($itemsResult)): 
                            $typeUnitArray   = json_decode($item['type_unit'] ?? '[]', true) ?: [];
                            $numberPartArray = json_decode($item['number_part'] ?? '[]', true) ?: [];
                            $typeUnitText   = implode(' / ', $typeUnitArray);
                            $numberPartText = implode('/', $numberPartArray);

                            $namaLengkap = htmlspecialchars($item['nama_sparepart']);
                            if (!empty($typeUnitText)) $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
                            if (!empty($numberPartText)) $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);

                            $kodeGabungan = !empty($item['kode_komponen'])
                                ? htmlspecialchars($item['kode_komponen'] . '-' . $item['kode_sparepart'])
                                : htmlspecialchars($item['kode_sparepart']);
                            
                            $qtyKirim   = (int)$item['quantity'];
                            $qtyTerima  = $item['quantity_diterima'] !== null ? (int)$item['quantity_diterima'] : 0;
                            $kekurangan = $qtyKirim - $qtyTerima;
                        ?>
                            <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 leading-relaxed"><?= $namaLengkap ?></p>
                                    <span class="font-mono text-[11px] text-slate-500 bg-slate-100 px-2 py-0.5 rounded mt-1 inline-block"><?= $kodeGabungan ?></span>
                                </div>
                                
                                <div class="shrink-0 flex items-center gap-4 bg-slate-50 px-4 py-2 rounded-xl border border-slate-100">
                                    <div class="text-center">
                                        <span class="block text-[9px] text-slate-400 font-medium uppercase tracking-wider">Dikirim</span>
                                        <span class="text-sm font-bold text-slate-600"><?= $qtyKirim ?></span>
                                    </div>
                                    <div class="text-center border-l border-slate-200 pl-4">
                                        <span class="block text-[9px] text-slate-400 font-medium uppercase tracking-wider">Diterima</span>
                                        <span class="text-sm font-bold text-slate-600"><?= $item['quantity_diterima'] !== null ? $qtyTerima : '-' ?></span>
                                    </div>
                                    <div class="text-center border-l border-slate-200 pl-4">
                                        <span class="block text-[9px] font-medium uppercase tracking-wider <?= $kekurangan > 0 ? 'text-red-400' : 'text-slate-400' ?>">Kurang</span>
                                        <span class="text-sm font-bold <?= $kekurangan > 0 ? 'text-red-600' : 'text-slate-600' ?>"><?= $kekurangan ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200"
                            onclick="return confirm('Apakah Anda yakin barang susulan siap dikirim dan ingin membuka akses input verifikasi di workshop?')">
                        <i class="ti ti-truck-delivery text-base"></i> Konfirmasi Kirim Susulan
                    </button>
                    <a href="../daftar-surat.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                        Batal
                    </a>
                </div>

            </div>

            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h3 class="font-bold text-slate-800 text-sm mb-3.5 tracking-wide uppercase">Detail Dokumen</h3>
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">No. Surat Jalan</span>
                            <span class="font-mono text-slate-700 font-bold block mt-0.5"><?= htmlspecialchars($data['nomor_surat']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Tujuan Penerima (User)</span>
                            <span class="font-semibold text-slate-800 block mt-0.5"><?= htmlspecialchars($data['nama_user']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Status Manifes Saat Ini</span>
                            <span class="font-semibold block mt-0.5 text-amber-600 uppercase text-xs"><?= htmlspecialchars($data['status']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border p-5 shadow-sm bg-blue-50 border-blue-100">
                    <div class="flex items-start gap-3">
                        <i class="ti ti-info-circle text-xl text-blue-500 shrink-0 mt-0.5"></i>
                        <div>
                            <h4 class="font-semibold text-blue-700 text-sm mb-1">Mekanisme Susulan</h4>
                            <p class="text-xs text-blue-600 leading-relaxed">
                                Menekan tombol konfirmasi akan mengubah status surat jalan kembali menjadi <strong>DIKIRIM</strong>. Data kuantitas penerimaan lama milik workshop tetap dipertahankan agar mereka bisa langsung mengupdate total akumulasi penerimaan barunya.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</main>

<?php include '../../layouts/footer.php'; ?>