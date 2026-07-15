<?php

require_once '../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../config/database.php';
require_once '../components/badge.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

$statusFilter = $_GET['status'] ?? 'semua'; // semua | draft | setuju | tolak

// ===== Statistik ringkasan =====
$statSql = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'setuju' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as menunggu,
        SUM(CASE WHEN status = 'tolak' THEN 1 ELSE 0 END) as ditolak
    FROM pengajuan_sparepart
";
$statStmt = mysqli_prepare($conn, $statSql);
mysqli_stmt_execute($statStmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($statStmt)) ?: [];

// ===== Setup pagination =====
$perPage     = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ===== Filter status (opsional) =====
$statusMap = [
    'draft'  => 'draft',
    'setuju' => 'setuju',
    'tolak'  => 'tolak',
];
$activeStatus = $statusMap[$statusFilter] ?? null;

// ===== Hitung total data untuk pagination =====
$countSql = "
    SELECT COUNT(*) as total
    FROM pengajuan_sparepart p
    JOIN sparepart s ON p.sparepart_id = s.id
    WHERE 1=1
";
$countParams = [];
$countTypes  = '';


if ($activeStatus !== null) {
    $countSql .= " AND p.status = ?";
    $countParams[] = $activeStatus;
    $countTypes   .= 's';
}

$countStmt = mysqli_prepare($conn, $countSql);

if (!empty($countTypes)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}

mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows   = mysqli_fetch_assoc($countResult)['total'];

// ===== Query data utama =====
$sql = "
    SELECT p.id, p.created_at, p.updated_at, p.quantity,
           p.foto_sparepart, p.kondisi_sparepart, p.keterangan,
           p.status, p.surat_jalan_id,
           s.nama_sparepart, s.kode_sparepart, s.number_part, s.type_unit,
           k.kode_komponen
    FROM pengajuan_sparepart p
    JOIN sparepart s ON p.sparepart_id = s.id
    LEFT JOIN komponen k ON s.komponen_id = k.id
    WHERE 1=1
";
$params = [];
$types  = '';


if ($activeStatus !== null) {
    $sql .= " AND p.status = ?";
    $params[] = $activeStatus;
    $types   .= 's';
}

$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = mysqli_prepare($conn, $sql);

if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ===== Susun data jadi array siap pakai =====
$submissions = [];
while ($data = mysqli_fetch_assoc($result)) {

    $badgeColor  = 'slate';
    $statusLabel = ucfirst($data['status']);
    if ($data['status'] === 'draft') {
        $badgeColor  = 'yellow';
        $statusLabel = 'Menunggu';
    } elseif ($data['status'] === 'setuju') {
        $badgeColor  = 'green';
        $statusLabel = 'Disetujui';
    } elseif ($data['status'] === 'tolak') {
        $badgeColor  = 'red';
        $statusLabel = 'Ditolak';
    }

    $typeUnitArray   = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    $typeUnitText   = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = htmlspecialchars($data['nama_sparepart']);
    if (!empty($typeUnitText))
        $namaLengkap .= ' / ' . htmlspecialchars($typeUnitText);
    if (!empty($numberPartText))
        $namaLengkap .= '/ ' . htmlspecialchars($numberPartText);

    $kodeGabungan = !empty($data['kode_komponen'])
        ? htmlspecialchars($data['kode_komponen'] . '-' . $data['kode_sparepart'])
        : htmlspecialchars($data['kode_sparepart']);

    $fotoList = [];
    if (!empty($data['foto_sparepart'])) {
        $decodedFoto = json_decode($data['foto_sparepart'], true);
        if (is_array($decodedFoto)) {
            foreach ($decodedFoto as $foto) {
                if (empty($foto))
                    continue;
                if (strpos($foto, 'data:') === 0 || strpos($foto, 'http') === 0) {
                    $fotoList[] = $foto;
                } elseif (strpos($foto, 'uploads/') !== false) {
                    $fotoList[] = '../' . ltrim($foto, '/');
                } else {
                    $fotoList[] = 'data:image/jpeg;base64,' . $foto;
                }
            }
        }
    }

    $submissions[] = [
        'id'             => (int) $data['id'],
        'status'         => $data['status'],
        'tanggal'        => date('d M Y', strtotime($data['created_at'])),
        'jam'            => date('H:i', strtotime($data['created_at'])),
        'nama'           => $namaLengkap,
        'kode'           => $kodeGabungan,
        'qty'            => (int) $data['quantity'],
        'kondisi'        => !empty($data['kondisi_sparepart']) ? htmlspecialchars($data['kondisi_sparepart']) : null,
        'keterangan'     => !empty($data['keterangan']) ? htmlspecialchars($data['keterangan']) : null,
        'foto'           => $fotoList,
        'surat_jalan_id' => $data['surat_jalan_id'] !== null ? (int) $data['surat_jalan_id'] : null,
        'status_label'   => $statusLabel,
        'status_color'   => $badgeColor,
    ];
}


?>

<main class="p-4 sm:p-6 lg:p-8 bg-slate-50 min-h-screen" x-data="{ 
    previewSrc: null, 
    openModal: false, 
    actionType: 'setuju',
    fokusFoto: 0,
    selectedItem: { id: '', nama: '', kode: '', qty: 0, foto: [] },
    
    bukaVerifikasi(el) {
        let base64Data = el.getAttribute('data-foto');
        let fotoArray = [];
        
        try {
            if (base64Data) {
                fotoArray = JSON.parse(atob(base64Data));
            }
        } catch (e) {
            console.error('Gagal memproses array foto:', e);
            fotoArray = [];
        }

        this.selectedItem = {
            id: el.getAttribute('data-id'),
            nama: el.getAttribute('data-nama'),
            kode: el.getAttribute('data-kode'),
            qty: parseInt(el.getAttribute('data-qty')),
            foto: fotoArray
        };
        
        this.fokusFoto = 0; 
        this.actionType = 'setuju';
        this.openModal = true;
    }
}">

    <?php if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'error'])): ?>
        <div class="mb-5 p-4 rounded-xl text-sm font-medium flex items-center gap-2.5
                    <?= $_GET['status'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
            <i class="ti <?= $_GET['status'] === 'success' ? 'ti-circle-check' : 'ti-alert-circle' ?> text-lg"></i>
            <?= $_GET['status'] === 'success' ? 'Verifikasi berhasil disimpan.' : 'Gagal memproses verifikasi.' ?>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-7">
        <div>
            <p class="text-xs font-semibold text-blue-600 mb-1 tracking-widest uppercase">Manajer Panel</p>
            <h1 class="text-2xl font-bold text-slate-900">Pengajuan Sparepart</h1>
            <p class="text-sm text-slate-500 mt-1">Berikan keputusan kuantitas atau tolak berkas pengajuan.</p>
        </div>
    </div>

    <!-- Statistik -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <i class="ti ti-folders text-xl text-blue-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Total Pengajuan</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight"><?= (int) ($stats['total'] ?? 0) ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
                <i class="ti ti-circle-check text-xl text-green-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Disetujui</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight"><?= (int) ($stats['disetujui'] ?? 0) ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center shrink-0">
                <i class="ti ti-clock-hour-4 text-xl text-yellow-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Menunggu</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight"><?= (int) ($stats['menunggu'] ?? 0) ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <i class="ti ti-circle-x text-xl text-red-600"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-500 font-medium">Ditolak</p>
                <h3 class="text-2xl font-bold text-slate-900 leading-tight"><?= (int) ($stats['ditolak'] ?? 0) ?></h3>
            </div>
        </div>

    </div>

    <!-- Empty state -->
    <?php if (empty($submissions)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="ti ti-package-off text-2xl text-slate-400"></i>
            </div>
            <p class="font-semibold text-slate-800">Tidak ada data</p>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($submissions as $item): ?>
                <div class="bg-white rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition-all duration-150 flex flex-col overflow-hidden">

                    <!-- Foto -->
                    <?php if (!empty($item['foto'])): ?>
                        <div class="relative w-full aspect-[16/10] bg-slate-900 group cursor-pointer overflow-hidden shrink-0"
                            @click="previewSrc = '<?= $item['foto'][0] ?>'">

                            <img src="<?= $item['foto'][0] ?>" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300">

                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 via-transparent to-transparent pointer-events-none"></div>

                            <div class="absolute top-2 right-2">
                                <?= renderBadge($item['status_label'], $item['status_color']) ?>
                            </div>

                            <?php if (count($item['foto']) > 1): ?>
                                <span class="absolute bottom-2 right-2 bg-black/60 text-white text-[11px] font-medium px-2 py-1 rounded-md flex items-center gap-1 backdrop-blur-sm">
                                    <i class="ti ti-photo text-xs"></i> <?= count($item['foto']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="relative w-full aspect-[16/10] bg-slate-50 flex flex-col items-center justify-center text-slate-300 gap-1 border-b border-slate-100 shrink-0">
                            <i class="ti ti-photo-off text-2xl"></i>
                            <span class="text-[11px] text-slate-400">Tidak ada foto</span>
                            <div class="absolute top-2 right-2">
                                <?= renderBadge($item['status_label'], $item['status_color']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Body -->
                    <div class="p-4 flex flex-col flex-1 gap-3">

                        <div>
                            <h4 class="font-semibold text-slate-800 text-sm leading-snug line-clamp-2" title="<?= $item['nama'] ?>">
                                <?= $item['nama'] ?>
                            </h4>
                            <p class="font-mono text-[11px] text-blue-600 font-medium bg-blue-50 px-1.5 py-0.5 rounded mt-1.5 inline-block">
                                <?= $item['kode'] ?>
                            </p>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 py-2.5 border-y border-slate-100">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-calendar-event text-slate-400"></i>
                                <?= $item['tanggal'] ?> &middot; <?= $item['jam'] ?>
                            </span>
                            <span class="font-semibold text-slate-700">
                                <?= $item['qty'] ?> <span class="text-slate-400 font-normal">pcs diminta</span>
                            </span>
                        </div>

                        <?php if ($item['kondisi']): ?>
                            <div class="bg-amber-50 rounded-lg px-2.5 py-2 border border-amber-100">
                                <p class="text-[10px] font-semibold text-amber-700 uppercase tracking-wide mb-0.5 flex items-center gap-1">
                                    <i class="ti ti-alert-triangle text-[11px]"></i> Alasan / Kondisi
                                </p>
                                <p class="text-[11px] text-amber-800 leading-relaxed line-clamp-2" title="<?= $item['kondisi'] ?>">
                                    <?= $item['kondisi'] ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="mt-auto pt-1">
                            <?php if ($item['status'] === 'draft'): ?>
                                <button type="button"
                                    data-id="<?= $item['id'] ?>"
                                    data-nama="<?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-kode="<?= $item['kode'] ?>"
                                    data-qty="<?= $item['qty'] ?>"
                                    data-foto="<?= base64_encode(json_encode($item['foto'])) ?>"
                                    @click="bukaVerifikasi($el)"
                                    class="w-full py-2.5 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
                                    <i class="ti ti-gavel text-sm"></i> Verifikasi
                                </button>
                            <?php else: ?>
                                <div class="w-full text-center py-2 px-3 bg-slate-50 text-slate-500 rounded-lg text-[11px] font-semibold border border-slate-200">
                                    Selesai Diproses
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <div class="mt-6">
        <?php include '../components/pagination.php'; ?>
    </div>

    <!-- Modal verifikasi -->
    <div x-show="openModal" x-cloak
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-transition>
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl overflow-hidden flex flex-col"
            @click.away="openModal = false">

            <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <h3 class="font-bold text-base">Form Keputusan Verifikasi</h3>
                <button type="button" @click="openModal = false" class="text-slate-400 hover:text-white">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 md:divide-x md:divide-slate-200 overflow-y-auto max-h-[80vh]">

                <div class="p-6 md:col-span-6 bg-slate-50 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-blue-600 tracking-wider uppercase">Item Terpilih</span>
                        <h5 class="font-semibold text-slate-800 text-base mt-0.5" x-text="selectedItem.nama"></h5>
                        <p class="font-mono text-xs text-slate-400 mt-0.5 mb-4" x-text="selectedItem.kode"></p>

                        <template x-if="selectedItem.foto && selectedItem.foto.length > 0">
                            <div>
                                <div class="relative w-full h-80 bg-slate-950 rounded-xl overflow-hidden group shadow-md flex items-center justify-center">
                                    <img :src="selectedItem.foto[fokusFoto]"
                                        class="w-full h-full object-contain cursor-zoom-in transition-all duration-200"
                                        @click="previewSrc = selectedItem.foto[fokusFoto]">

                                    <template x-if="selectedItem.foto.length > 1">
                                        <button type="button"
                                            @click="fokusFoto = (fokusFoto === 0) ? selectedItem.foto.length - 1 : fokusFoto - 1"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-slate-900/80 text-white flex items-center justify-center hover:bg-slate-900 shadow opacity-0 group-hover:opacity-100 z-10">
                                            <i class="ti ti-chevron-left text-xl"></i>
                                        </button>
                                    </template>

                                    <template x-if="selectedItem.foto.length > 1">
                                        <button type="button"
                                            @click="fokusFoto = (fokusFoto === selectedItem.foto.length - 1) ? 0 : fokusFoto + 1"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-slate-900/80 text-white flex items-center justify-center hover:bg-slate-900 shadow opacity-0 group-hover:opacity-100 z-10">
                                            <i class="ti ti-chevron-right text-xl"></i>
                                        </button>
                                    </template>

                                    <div class="absolute bottom-3 right-3 bg-slate-900/80 backdrop-blur-sm text-white text-[11px] px-3 py-1 rounded-full font-semibold z-10">
                                        <span x-text="(fokusFoto + 1)"></span> / <span x-text="selectedItem.foto.length"></span>
                                    </div>
                                </div>

                                <div class="flex gap-2 mt-3 overflow-x-auto pb-1">
                                    <template x-for="(pic, index) in selectedItem.foto">
                                        <button type="button" @click="fokusFoto = index"
                                            class="w-14 h-14 object-cover rounded-lg border-2 transition-all shrink-0 overflow-hidden bg-slate-200"
                                            :class="fokusFoto === index ? 'border-blue-500 ring-2 ring-blue-500/20 scale-95' : 'border-slate-300 opacity-60 hover:opacity-100'">
                                            <img :src="pic" class="w-full h-full object-cover">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="!selectedItem.foto || selectedItem.foto.length === 0">
                            <div class="w-full h-80 bg-slate-100 rounded-xl flex flex-col items-center justify-center text-slate-400 gap-1.5">
                                <i class="ti ti-photo-off text-3xl"></i>
                                <span class="text-xs">Tidak ada foto dilampirkan</span>
                            </div>
                        </template>
                    </div>
                </div>

                <form action="pengajuan/approve-pengajuan.php" method="POST"
                    class="p-6 md:col-span-6 space-y-4 flex flex-col justify-between">
                    <input type="hidden" name="id" :value="selectedItem.id">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Keputusan</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                    :class="actionType === 'setuju' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'">
                                    <input type="radio" name="status" value="setuju" x-model="actionType" class="sr-only">
                                    <i class="ti ti-circle-check text-base"></i>
                                    <span class="text-sm font-medium">Setujui</span>
                                </label>
                                <label class="flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                    :class="actionType === 'tolak' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'">
                                    <input type="radio" name="status" value="tolak" x-model="actionType" class="sr-only">
                                    <i class="ti ti-circle-x text-base"></i>
                                    <span class="text-sm font-medium">Tolak</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="actionType === 'setuju'" x-transition>
                            <label for="quantity" class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">
                                Jumlah yang Disetujui (pcs)
                            </label>
                            <input type="number" name="quantity" id="quantity"
                                min="1"
                                :value="selectedItem.qty > 0 ? selectedItem.qty : 1"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-sm font-medium">
                            <p class="text-[11px] text-slate-400 mt-1.5">
                                Diminta <span x-text="selectedItem.qty"></span> pcs. Jumlah yang disetujui boleh berbeda dari yang diminta.
                            </p>
                        </div>

                        <div>
                            <label for="keterangan" class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">
                                Catatan
                            </label>
                            <textarea name="keterangan" id="keterangan" rows="4"
                                placeholder="Masukkan alasan penolakan atau catatan tambahan untuk warehouse..."
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-sm placeholder:text-slate-400"></textarea>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-slate-100">
                        <button type="button" @click="openModal = false"
                            class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl text-sm transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                            class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl text-sm transition-colors shadow-sm">
                            Simpan Keputusan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Modal preview foto tunggal -->
    <div x-show="previewSrc" x-cloak
        class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4"
        @click.self="previewSrc = null"
        @keydown.escape.window="previewSrc = null">
        <button type="button" @click="previewSrc = null"
            class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20">
            <i class="ti ti-x text-xl"></i>
        </button>
        <img :src="previewSrc" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain">
    </div>
</main>

<?php include '../layouts/footer.php'; ?>