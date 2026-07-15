<?php

require_once '../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$userId = $_SESSION['user_id'];

// ===== Ambil SEMUA pengiriman milik user ini, semua status =====
$sql = "
    SELECT sj.id, sj.nomor_surat, sj.tanggal_kirim, sj.status
    FROM surat_jalan sj
    WHERE sj.user_id = ?
    ORDER BY sj.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pengirimanList = [];
$pengirimanIds  = [];

while ($data = mysqli_fetch_assoc($result)) {
    $pengirimanList[$data['id']] = [
        'id'            => $data['id'],
        'nomor_surat'   => $data['nomor_surat'],
        'tanggal_kirim' => $data['tanggal_kirim'],
        'status'        => $data['status'],
        'items'         => [],
    ];
    $pengirimanIds[] = $data['id'];
}

// ===== Ambil semua detail item untuk surat-surat di atas =====
if (!empty($pengirimanIds)) {

    $placeholders = implode(',', array_fill(0, count($pengirimanIds), '?'));
    $types        = str_repeat('i', count($pengirimanIds));

    $detailSql = "
        SELECT sjd.id, sjd.surat_jalan_id, sjd.quantity, sjd.quantity_diterima,
               s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit,
               k.kode_komponen
        FROM surat_jalan_detail sjd
        JOIN sparepart s ON s.id = sjd.sparepart_id
        LEFT JOIN komponen k ON k.id = s.komponen_id
        WHERE sjd.surat_jalan_id IN ($placeholders)
    ";

    $detailStmt = mysqli_prepare($conn, $detailSql);
    mysqli_stmt_bind_param($detailStmt, $types, ...$pengirimanIds);
    mysqli_stmt_execute($detailStmt);
    $detailResult = mysqli_stmt_get_result($detailStmt);

    while ($detail = mysqli_fetch_assoc($detailResult)) {

        $typeUnitArray   = json_decode($detail['type_unit'] ?? '[]', true) ?: [];
        $numberPartArray = json_decode($detail['number_part'] ?? '[]', true) ?: [];

        $typeUnitText   = implode(' / ', $typeUnitArray);
        $numberPartText = implode('/', $numberPartArray);

        $namaLengkap = $detail['nama_sparepart'];
        if (!empty($typeUnitText))   $namaLengkap .= ' / ' . $typeUnitText;
        if (!empty($numberPartText)) $namaLengkap .= '/ ' . $numberPartText;

        $kodeGabungan = !empty($detail['kode_komponen'])
            ? $detail['kode_komponen'] . '-' . $detail['kode_sparepart']
            : $detail['kode_sparepart'];

        $pengirimanList[$detail['surat_jalan_id']]['items'][] = [
            'detail_id'         => $detail['id'],
            'kode'              => $kodeGabungan,
            'nama'              => $namaLengkap,
            'quantity'          => $detail['quantity'],
            'quantity_diterima' => $detail['quantity_diterima'],
        ];
    }
}

if (!empty($_SESSION['form_success'])): $formSuccess = $_SESSION['form_success']; unset($_SESSION['form_success']); endif;
if (!empty($_SESSION['form_error'])): $formError = $_SESSION['form_error']; unset($_SESSION['form_error']); endif;

/**
 * Mapping status transaksi -> label, warna badge, dan style aksen kartu.
 * Sesuaikan value status di sini kalau nama status di database berbeda.
 */
function statusBadgeInfo(string $status): array
{
    $map = [
        'draft'              => ['label' => 'Draft',             'color' => 'gray',   'accent' => 'bg-slate-300',  'ring' => ''],
        'dikirim'            => ['label' => 'Dikirim',            'color' => 'blue',   'accent' => 'bg-blue-400',   'ring' => 'ring-1 ring-blue-100'],
        'diterima'           => ['label' => 'Diterima',           'color' => 'green',  'accent' => 'bg-green-400',  'ring' => ''],
        'diterima_sebagian'  => ['label' => 'Diterima Sebagian',  'color' => 'yellow', 'accent' => 'bg-yellow-400', 'ring' => ''],
        'ditolak'            => ['label' => 'Ditolak',            'color' => 'red',    'accent' => 'bg-red-400',    'ring' => ''],
        'batal'              => ['label' => 'Dibatalkan',         'color' => 'red',    'accent' => 'bg-red-300',    'ring' => ''],
    ];

    return $map[$status] ?? ['label' => ucfirst($status), 'color' => 'gray', 'accent' => 'bg-slate-300', 'ring' => ''];
}

// Ringkasan cepat untuk header
$totalPengiriman = count($pengirimanList);
$totalPending     = 0;
foreach ($pengirimanList as $p) {
    if ($p['status'] === 'dikirim') $totalPending++;
}

?>
<main
    class="p-6 bg-slate-50 min-h-screen"
    x-data="{ expanded: {} }">

    <!-- Page Header -->
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Workshop Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Daftar Pengiriman</h1>
            <p class="text-slate-500 mt-1 text-sm">Riwayat dan konfirmasi penerimaan sparepart dari warehouse.</p>
        </div>

        <?php if ($totalPengiriman > 0): ?>
        <div class="flex items-center gap-3">
            <div class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                    <i class="ti ti-truck text-sm text-slate-500"></i>
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-slate-800"><?= $totalPengiriman ?></p>
                    <p class="text-[11px] text-slate-400">Total kiriman</p>
                </div>
            </div>
            <?php if ($totalPending > 0): ?>
            <div class="px-4 py-2.5 rounded-xl bg-blue-50 border border-blue-100 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="ti ti-clock-hour-4 text-sm text-blue-500"></i>
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-blue-700"><?= $totalPending ?></p>
                    <p class="text-[11px] text-blue-400">Perlu konfirmasi</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($formSuccess)): ?>
    <div class="mb-6 px-4 py-3 rounded-xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2">
        <i class="ti ti-circle-check text-base shrink-0"></i>
        <?= htmlspecialchars($formSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($formError)): ?>
    <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
        <i class="ti ti-alert-circle text-base shrink-0"></i>
        <?= htmlspecialchars($formError) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($pengirimanList)): ?>

    <div class="bg-white rounded-2xl border border-dashed border-slate-200 flex flex-col items-center justify-center py-16 px-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
            <i class="ti ti-truck-off text-3xl text-slate-400"></i>
        </div>
        <p class="text-slate-600 text-sm font-medium">Belum ada transaksi pengiriman</p>
        <p class="text-slate-400 text-xs mt-1">Kiriman dari warehouse akan muncul di sini.</p>
    </div>

    <?php else: ?>

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">

        <?php foreach ($pengirimanList as $pengiriman):
            $isPending = $pengiriman['status'] === 'dikirim';
            $badge     = statusBadgeInfo($pengiriman['status']);

            // Ringkasan progres penerimaan untuk kartu non-pending
            $totalQty     = 0;
            $totalReceived = 0;
            foreach ($pengiriman['items'] as $it) {
                $totalQty     += (int)$it['quantity'];
                $totalReceived += $it['quantity_diterima'] !== null ? (int)$it['quantity_diterima'] : 0;
            }
            $isPartial = !$isPending && $totalQty > 0 && $totalReceived < $totalQty;
        ?>
        <div
            x-data="{ showConfirmModal: false }"
            class="group bg-white rounded-2xl shadow-sm border border-slate-200 <?= $badge['ring'] ?> overflow-hidden flex flex-col transition-shadow hover:shadow-md">

            <?php if ($isPending): ?>
            <form action="surat/approve-sparepart.php" method="POST" class="flex flex-col flex-1">
                <input type="hidden" name="surat_id" value="<?= $pengiriman['id'] ?>">
            <?php endif; ?>

                <!-- Aksen status di sisi kiri -->
                <div class="flex flex-1">
                    <div class="w-1 shrink-0 <?= $badge['accent'] ?>"></div>

                    <div class="flex-1 min-w-0 flex flex-col">

                        <!-- Header card -->
                        <div class="p-5 pb-4 border-b border-slate-100">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-[11px] text-slate-400 mb-1 truncate">
                                        <?= htmlspecialchars($pengiriman['nomor_surat']) ?>
                                    </p>
                                    <h3 class="font-semibold text-slate-800 flex items-center gap-1.5">
                                        <i class="ti ti-building-warehouse text-sm text-slate-400"></i>
                                        Dari Warehouse
                                    </h3>
                                </div>
                                <?= renderBadge($badge['label'], $badge['color']) ?>
                            </div>

                            <div class="flex items-center gap-4 text-xs text-slate-500">
                                <span class="flex items-center gap-1.5">
                                    <i class="ti ti-calendar text-sm"></i>
                                    <?= $pengiriman['tanggal_kirim'] ? date('d M Y', strtotime($pengiriman['tanggal_kirim'])) : '-' ?>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="ti ti-box text-sm"></i>
                                    <?= count($pengiriman['items']) ?> item
                                </span>
                                <?php if ($isPartial): ?>
                                <span class="flex items-center gap-1.5 text-yellow-600 font-medium">
                                    <i class="ti ti-alert-triangle text-sm"></i>
                                    <?= $totalReceived ?>/<?= $totalQty ?> pcs
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Toggle detail -->
                        <button
                            type="button"
                            @click="expanded['<?= $pengiriman['id'] ?>'] = !expanded['<?= $pengiriman['id'] ?>']"
                            class="w-full flex items-center justify-between px-5 py-3 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                            <span class="flex items-center gap-1.5 font-medium">
                                <i class="ti <?= $isPending ? 'ti-clipboard-check' : 'ti-list-details' ?> text-base"></i>
                                <?= $isPending ? 'Konfirmasi Jumlah Diterima' : 'Lihat Detail Item' ?>
                            </span>
                            <i class="ti ti-chevron-down text-base transition-transform duration-200 text-slate-400"
                                :class="expanded['<?= $pengiriman['id'] ?>'] ? 'rotate-180' : ''"></i>
                        </button>

                        <!-- Detail items, collapsible -->
                        <div
                            x-show="expanded['<?= $pengiriman['id'] ?>']"
                            x-collapse
                            x-cloak
                            class="px-5 pb-4 border-t border-slate-100">

                            <?php if ($isPending): ?>
                            <p class="text-xs text-slate-400 pt-3 pb-1 leading-relaxed">
                                Ubah angka pada kolom "Diterima" jika ada barang susulan baru yang masuk, atau sesuaikan total riil barang yang telah diterima.
                            </p>
                            <?php endif; ?>

                            <div class="divide-y divide-slate-50">
                                <?php foreach ($pengiriman['items'] as $item):
                                    $valueDefaultInput = $item['quantity_diterima'] !== null ? (int)$item['quantity_diterima'] : (int)$item['quantity'];
                                    $itemShort = !$isPending && $item['quantity_diterima'] !== null && (int)$item['quantity_diterima'] < (int)$item['quantity'];
                                ?>
                                <div class="flex items-center justify-between gap-3 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-slate-700 font-medium truncate">
                                            <?= htmlspecialchars($item['nama']) ?>
                                        </p>
                                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                                            <?= htmlspecialchars($item['kode']) ?>
                                        </p>
                                    </div>

                                    <?php if ($isPending): ?>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <!-- Quantity dikirim -->
                                        <div class="flex flex-col items-center gap-1">
                                            <label class="text-[10px] text-slate-400 uppercase tracking-wide">Dikirim</label>
                                            <input
                                                type="number"
                                                value="<?= $item['quantity'] ?>"
                                                disabled
                                                class="w-16 px-2 py-1.5 text-sm text-center rounded-lg border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed">
                                            <input type="hidden" name="quantity[<?= $item['detail_id'] ?>]" value="<?= $item['quantity'] ?>">
                                        </div>

                                        <i class="ti ti-arrow-right text-slate-300 text-sm mt-4"></i>

                                        <!-- Quantity diterima -->
                                        <div class="flex flex-col items-center gap-1">
                                            <label class="text-[10px] text-slate-400 uppercase tracking-wide">Diterima</label>
                                            <input
                                                type="number"
                                                name="qty_diterima[<?= $item['detail_id'] ?>]"
                                                value="<?= $valueDefaultInput ?>"
                                                min="0"
                                                max="<?= $item['quantity'] ?>"
                                                required
                                                class="w-16 px-2 py-1.5 text-sm text-center rounded-lg border border-slate-200 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition">
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="shrink-0 flex items-center gap-2 text-sm">
                                        <span class="text-slate-500 tabular-nums"><?= $item['quantity'] ?></span>
                                        <i class="ti ti-arrow-right text-slate-300 text-sm"></i>
                                        <span class="px-2 py-0.5 rounded-md font-medium tabular-nums <?= $itemShort ? 'bg-yellow-50 text-yellow-700' : 'bg-slate-50 text-slate-700' ?>">
                                            <?= $item['quantity_diterima'] !== null ? $item['quantity_diterima'] : '-' ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                        </div>

                        <?php if ($isPending): ?>
                        <!-- Footer: tombol Terima, buka modal konfirmasi -->
                        <div class="mt-auto px-5 py-4 bg-slate-50/50 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showConfirmModal = true"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-green-500 text-white text-sm font-semibold shadow-sm shadow-green-500/25 hover:shadow-lg hover:shadow-green-500/25 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200">
                                <i class="ti ti-check text-base"></i>
                                Terima Barang
                            </button>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <?php if ($isPending): ?>
                <!-- Modal Konfirmasi Terima -->
                <div
                    x-show="showConfirmModal"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                    @click.self="showConfirmModal = false">

                    <div
                        x-show="showConfirmModal"
                        x-transition.scale.95
                        class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">

                        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mb-4 mx-auto">
                            <i class="ti ti-truck-delivery text-2xl text-green-500"></i>
                        </div>

                        <h3 class="text-lg font-semibold text-slate-800 text-center mb-2">
                            Konfirmasi Penerimaan
                        </h3>

                        <p class="text-sm text-slate-500 text-center mb-6 leading-relaxed">
                            Konfirmasi pengiriman <strong class="text-slate-700"><?= htmlspecialchars($pengiriman['nomor_surat']) ?></strong>?
                            Pastikan jumlah diterima sudah sesuai kondisi fisik barang terbaru. Stok akan bertambah secara akumulatif di workshop kamu berdasarkan selisih barang susulan yang baru masuk.
                        </p>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="showConfirmModal = false"
                                class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition-colors text-sm">
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-green-500 text-white font-medium hover:shadow-lg hover:shadow-green-500/25 transition-all duration-200 text-sm">
                                Ya, Terima
                            </button>
                        </div>

                    </div>

                </div>
                <?php endif; ?>

            <?php if ($isPending): ?>
            </form>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>

</main>

<?php include '../layouts/footer.php'; ?>