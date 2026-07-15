<?php

require_once '../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// ===== Ambil semua surat jalan + nama user =====
$sql = "
    SELECT sj.id, sj.nomor_surat, sj.tanggal_kirim, sj.tanggal_terima, sj.status,
           u.nama as nama_user
    FROM surat_jalan sj
    JOIN users u ON u.id = sj.user_id
    ORDER BY sj.created_at DESC
";

$result = mysqli_query($conn, $sql);

$suratList = [];
$suratIds = [];

while ($data = mysqli_fetch_assoc($result)) {
    $suratList[$data['id']] = [
        'id' => $data['id'],
        'nomor_surat' => $data['nomor_surat'],
        'tanggal_kirim' => $data['tanggal_kirim'],
        'tanggal_terima' => $data['tanggal_terima'],
        'status' => $data['status'],
        'nama_user' => $data['nama_user'],
        'items' => [],
    ];  
    $suratIds[] = $data['id'];
}

// ===== Ambil semua detail item untuk surat-surat di atas (satu query, bukan N+1) =====
if (!empty($suratIds)) {

    $placeholders = implode(',', array_fill(0, count($suratIds), '?'));
    $types = str_repeat('i', count($suratIds));

    $detailSql = "
        SELECT sjd.surat_jalan_id, sjd.quantity, sjd.quantity_diterima,
               s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit,
               k.kode_komponen
        FROM surat_jalan_detail sjd
        JOIN sparepart s ON s.id = sjd.sparepart_id
        LEFT JOIN komponen k ON k.id = s.komponen_id
        WHERE sjd.surat_jalan_id IN ($placeholders)
    ";

    $detailStmt = mysqli_prepare($conn, $detailSql);
    mysqli_stmt_bind_param($detailStmt, $types, ...$suratIds);
    mysqli_stmt_execute($detailStmt);
    $detailResult = mysqli_stmt_get_result($detailStmt);

    while ($detail = mysqli_fetch_assoc($detailResult)) {

        $typeUnitArray = json_decode($detail['type_unit'] ?? '[]', true) ?: [];
        $numberPartArray = json_decode($detail['number_part'] ?? '[]', true) ?: [];

        $typeUnitText = implode(' / ', $typeUnitArray);
        $numberPartText = implode('/', $numberPartArray);

        $namaLengkap = $detail['nama_sparepart'];
        if (!empty($typeUnitText))
            $namaLengkap .= ' / ' . $typeUnitText;
        if (!empty($numberPartText))
            $namaLengkap .= '/ ' . $numberPartText;

        $kodeGabungan = !empty($detail['kode_komponen'])
            ? $detail['kode_komponen'] . '-' . $detail['kode_sparepart']
            : $detail['kode_sparepart'];

        $suratList[$detail['surat_jalan_id']]['items'][] = [
            'kode' => $kodeGabungan,
            'nama' => $namaLengkap,
            'quantity' => $detail['quantity'],
            'quantity_diterima' => $detail['quantity_diterima'],
        ];
    }
}

if (!empty($_SESSION['form_success'])):
    $formSuccess = $_SESSION['form_success'];
    unset($_SESSION['form_success']);
endif;
if (!empty($_SESSION['form_error'])):
    $formError = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
endif;

// Helper untuk styling badge & aksen status
function statusBadgeColor(string $status): string
{
    return match ($status) {
        'draft' => 'slate',
        'dikirim' => 'blue',
        'diterima' => 'green',
        'diterima_sebagian' => 'yellow',
        'ditolak' => 'red',
        default => 'slate',
    };
}

function statusBadgeLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'dikirim' => 'Dikirim',
        'diterima' => 'Diterima',
        'diterima_sebagian' => 'Diterima Sebagian',
        'ditolak' => 'Ditolak',
        default => ucfirst($status),
    };
}

function statusAccentClass(string $status): string
{
    return match ($status) {
        'draft' => 'bg-slate-300',
        'dikirim' => 'bg-blue-400',
        'diterima' => 'bg-green-400',
        'diterima_sebagian' => 'bg-amber-400',
        'ditolak' => 'bg-red-400',
        default => 'bg-slate-300',
    };
}

// Ringkasan cepat untuk header
$totalSurat = count($suratList);
$totalDraft = 0;
$totalPerluTindakan = 0; // draft (belum dikirim) + diterima_sebagian (perlu kirim susulan)
foreach ($suratList as $s) {
    if ($s['status'] === 'draft') $totalDraft++;
    if (in_array($s['status'], ['draft', 'diterima_sebagian'])) $totalPerluTindakan++;
}

?>
<main class="p-6 bg-slate-50 min-h-screen" x-data="{ expanded: {} }">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">Warehouse Panel</p>
            <h1 class="text-2xl font-bold text-slate-800">Daftar Pengiriman</h1>
            <p class="text-slate-500 mt-1 text-sm">Kelola pengiriman sparepart ke workshop.</p>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($totalSurat > 0): ?>
            <div class="hidden sm:flex items-center gap-3">
                <div class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                        <i class="ti ti-file-text text-sm text-slate-500"></i>
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-slate-800"><?= $totalSurat ?></p>
                        <p class="text-[11px] text-slate-400">Total surat jalan</p>
                    </div>
                </div>
                <?php if ($totalPerluTindakan > 0): ?>
                <div class="px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-100 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                        <i class="ti ti-alert-circle text-sm text-amber-500"></i>
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-amber-700"><?= $totalPerluTindakan ?></p>
                        <p class="text-[11px] text-amber-500">Perlu tindakan</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="pengiriman/create-pengiriman.php"
                class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold shadow-sm shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 whitespace-nowrap">
                <i class="ti ti-plus text-base"></i>
                Buat Pengiriman
            </a>
        </div>
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

    <?php if (empty($suratList)): ?>

        <div class="bg-white rounded-2xl border border-dashed border-slate-200 flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="ti ti-file-off text-3xl text-slate-400"></i>
            </div>
            <p class="text-slate-600 text-sm font-medium">Belum ada surat jalan yang dibuat</p>
            <p class="text-slate-400 text-xs mt-1">Klik "Buat Pengiriman" untuk membuat surat jalan baru.</p>
        </div>

    <?php else: ?>

        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">

            <?php foreach ($suratList as $surat): ?>
                <div class="group bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col transition-shadow hover:shadow-md">

                    <div class="flex flex-1">
                        <div class="w-1 shrink-0 <?= statusAccentClass($surat['status']) ?>"></div>

                        <div class="flex-1 min-w-0 flex flex-col">

                            <div class="p-5 pb-4 border-b border-slate-100">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="font-mono text-[11px] text-slate-400 mb-1 truncate">
                                            <?= htmlspecialchars($surat['nomor_surat']) ?>
                                        </p>
                                        <h3 class="font-semibold text-slate-800 truncate flex items-center gap-1.5">
                                            <i class="ti ti-user text-sm text-slate-400"></i>
                                            <?= htmlspecialchars($surat['nama_user']) ?>
                                        </h3>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <?= renderBadge(statusBadgeLabel($surat['status']), statusBadgeColor($surat['status'])) ?>

                                        <?php if ($surat['status'] === 'diterima_sebagian'): ?>
                                            <!-- Tombol edit pensil aktif dan mengarah ke konfirmasi susulan jika statusnya diterima sebagian -->
                                            <a href="daftar-surat/edit-daftar-surat.php?id=<?= $surat['id'] ?>"
                                                class="w-7 h-7 flex items-center justify-center rounded-lg border border-amber-200 text-amber-600 bg-amber-50 hover:bg-amber-100 transition-colors shadow-sm"
                                                title="Proses Kirim Susulan">
                                                <i class="ti ti-pencil text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 text-xs text-slate-500">
                                    <span class="flex items-center gap-1.5">
                                        <i class="ti ti-calendar text-sm"></i>
                                        <?= $surat['tanggal_kirim'] ? date('d M Y', strtotime($surat['tanggal_kirim'])) : '-' ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="ti ti-box text-sm"></i>
                                        <?= count($surat['items']) ?> item
                                    </span>
                                </div>
                            </div>

                            <button @click="expanded['<?= $surat['id'] ?>'] = !expanded['<?= $surat['id'] ?>']"
                                class="w-full flex items-center justify-between px-5 py-3 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                                <span class="flex items-center gap-1.5 font-medium">
                                    <i class="ti ti-list-details text-base"></i>
                                    Lihat Detail &amp; Kuantiti Real
                                </span>
                                <i class="ti ti-chevron-down text-base transition-transform duration-200 text-slate-400"
                                    :class="expanded['<?= $surat['id'] ?>'] ? 'rotate-180' : ''"></i>
                            </button>

                            <div x-show="expanded['<?= $surat['id'] ?>']" x-collapse x-cloak
                                class="px-5 pb-2 border-t border-slate-100 bg-slate-50/40">

                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($surat['items'] as $item):
                                        $isShort = $item['quantity_diterima'] !== null && (int)$item['quantity_diterima'] < (int)$item['quantity'];
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

                                            <div class="shrink-0 flex items-center gap-2 text-sm">
                                                <span class="text-slate-500 tabular-nums"><?= $item['quantity'] ?></span>
                                                <i class="ti ti-arrow-right text-slate-300 text-sm"></i>
                                                <span class="px-2 py-0.5 rounded-md font-medium tabular-nums <?= $isShort ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700' ?>">
                                                    <?= $item['quantity_diterima'] !== null ? $item['quantity_diterima'] : '-' ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            </div>

                            <div class="mt-auto px-5 py-4 bg-slate-50/50 border-t border-slate-100">
                                <?php if ($surat['status'] === 'draft'): ?>

                                    <form action="surat/kirim-sparepart.php" method="POST">
                                        <input type="hidden" name="surat_id" value="<?= htmlspecialchars($surat['id']) ?>">
                                        <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-semibold shadow-sm shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200">
                                            <i class="ti ti-send text-base"></i>
                                            Kirim
                                        </button>
                                    </form>

                                <?php elseif ($surat['status'] === 'dikirim'): ?>

                                    <div class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-50 text-blue-600 text-sm font-medium">
                                        <i class="ti ti-truck text-base"></i>
                                        Sedang dalam pengiriman
                                    </div>

                                <?php elseif ($surat['status'] === 'diterima_sebagian'): ?>

                                    <div class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 text-amber-600 text-sm font-medium border border-amber-100 text-center">
                                        <i class="ti ti-alert-circle text-base shrink-0"></i>
                                        <span>Diterima Sebagian<?= $surat['tanggal_terima'] ? ' &middot; ' . date('d M Y', strtotime($surat['tanggal_terima'])) : '' ?></span>
                                    </div>

                                <?php elseif ($surat['status'] === 'ditolak'): ?>

                                    <div class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-50 text-red-600 text-sm font-medium">
                                        <i class="ti ti-circle-x text-base"></i>
                                        Pengiriman Ditolak / Dibatalkan
                                    </div>

                                <?php else: ?>

                                    <div class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-green-50 text-green-600 text-sm font-medium text-center">
                                        <i class="ti ti-circle-check text-base shrink-0"></i>
                                        <span>Diterima Penuh<?= $surat['tanggal_terima'] ? ' &middot; ' . date('d M Y', strtotime($surat['tanggal_terima'])) : '' ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include '../layouts/footer.php'; ?>