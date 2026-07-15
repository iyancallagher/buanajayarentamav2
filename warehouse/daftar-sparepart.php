<?php

require_once '../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../config/database.php';

$menuFile = __DIR__ . '/menu.php';

include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../layouts/navbar.php';

// Ambil hanya pengajuan dengan status 'setuju'
$sql = "
    SELECT p.id, p.quantity, p.keterangan, p.user_id,
           s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit,
           k.kode_komponen,
           u.nama as nama_user
    FROM pengajuan_sparepart p
    JOIN sparepart s ON s.id = p.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'setuju' AND p.surat_jalan_id IS NULL
    ORDER BY p.created_at DESC
";

$result = mysqli_query($conn, $sql);

$pengajuanList = [];
while ($data = mysqli_fetch_assoc($result)) {

    $typeUnitArray = json_decode($data['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];

    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = $data['nama_sparepart'];
    if (!empty($typeUnitText))
        $namaLengkap .= ' / ' . $typeUnitText;
    if (!empty($numberPartText))
        $namaLengkap .= '/ ' . $numberPartText;

    $kodeGabungan = !empty($data['kode_komponen'])
        ? $data['kode_komponen'] . '-' . $data['kode_sparepart']
        : $data['kode_sparepart'];

    $pengajuanList[] = [
        'id' => $data['id'],
        'kode' => $kodeGabungan,
        'nama' => $namaLengkap,
        'quantity' => $data['quantity'],
        'keterangan' => $data['keterangan'],
        'user_id' => $data['user_id'],
        'nama_user' => $data['nama_user'],
    ];
}

if (!empty($_SESSION['form_success'])):
    $formSuccess = $_SESSION['form_success'];
    unset($_SESSION['form_success']);
endif;
if (!empty($_SESSION['form_error'])):
    $formError = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
endif;

?>
<main class="p-4 sm:p-6 lg:p-8 bg-slate-50 min-h-screen" x-data="{
        pengajuanList: <?= htmlspecialchars(json_encode($pengajuanList), ENT_QUOTES) ?>,
        selected: [],

        toggleSelect(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(item => item !== id);
            } else {
                this.selected.push(id);
            }
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        toggleSelectAll() {
            if (this.selected.length === this.pengajuanList.length) {
                this.selected = [];
            } else {
                this.selected = this.pengajuanList.map(item => item.id);
            }
        },

        get allSelected() {
            return this.pengajuanList.length > 0 && this.selected.length === this.pengajuanList.length;
        },

        get selectedItems() {
            return this.pengajuanList.filter(item => this.selected.includes(item.id));
        },

        get totalQuantitySelected() {
            return this.selectedItems.reduce((sum, item) => sum + parseInt(item.quantity), 0);
        },

        get uniqueUserIds() {
            return [...new Set(this.selectedItems.map(item => item.user_id))];
        },

        get hasMixedUser() {
            return this.uniqueUserIds.length > 1;
        },

        get selectedUserName() {
            if (this.selectedItems.length === 0) return '';
            return this.selectedItems[0].nama_user;
        }
    }">

    <div class="mb-6">
        <p class="text-xs font-semibold text-blue-600 mb-1 tracking-widest uppercase">Warehouse Panel</p>
        <h1 class="text-2xl font-bold text-slate-900">Daftar Sparepart Disetujui</h1>
        <p class="text-sm text-slate-500 mt-1">Pilih sparepart yang akan dimasukkan ke dalam pengiriman.</p>
    </div>

    <?php if (!empty($formSuccess)): ?>
        <div
            class="mb-5 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium flex items-center gap-2.5">
            <i class="ti ti-circle-check text-lg shrink-0"></i>
            <?= htmlspecialchars($formSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($formError)): ?>
        <div
            class="mb-5 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-center gap-2.5">
            <i class="ti ti-alert-circle text-lg shrink-0"></i>
            <?= htmlspecialchars($formError) ?>
        </div>
    <?php endif; ?>

    <div x-show="hasMixedUser" x-cloak x-transition.opacity
        class="mb-5 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-center gap-2.5">
        <i class="ti ti-alert-triangle text-lg shrink-0"></i>
        Tidak bisa membuat satu surat untuk pengajuan dari user yang berbeda. Pilih sparepart dari satu user yang sama.
    </div>

    <div x-show="selected.length > 0" x-cloak x-transition.opacity
        class="mb-5 px-5 py-4 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-sm shrink-0"
                x-text="selected.length"></div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-blue-800">
                    <span x-text="selected.length"></span> sparepart dipilih
                    <span class="text-blue-600 font-normal">&middot; <span x-text="totalQuantitySelected"></span> 
                        total</span>
                </p>
                <p class="text-xs text-blue-600 mt-0.5" x-show="!hasMixedUser"
                    x-text="'Diajukan oleh: ' + selectedUserName"></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="selected = []"
                class="px-4 py-2.5 rounded-xl text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                Batal Pilih
            </button>

            <form action="surat/cetak-surat.php" method="POST" @submit="if (hasMixedUser) { $event.preventDefault(); }">
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="pengajuan_ids[]" :value="id">
                </template>
                <button type="submit" :disabled="hasMixedUser"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="ti ti-printer text-base"></i>
                    Buat Pengiriman
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Sparepart Disetujui</h2>
            <?php if (!empty($pengajuanList)): ?>
                <span class="text-xs font-medium text-slate-400"><?= count($pengajuanList) ?> item menunggu surat
                    jalan</span>
            <?php endif; ?>
        </div>

        <?php if (empty($pengajuanList)): ?>
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                    <i class="ti ti-database-off text-2xl text-slate-400"></i>
                </div>
                <p class="font-semibold text-slate-800">Belum ada pengajuan disetujui</p>
                <p class="text-sm text-slate-500 mt-1 max-w-xs">
                    Pengajuan yang sudah disetujui manajer dan belum dibuatkan pengiriman akan muncul di sini.
                </p>
            </div>
        <?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-3 w-12">
                                <input type="checkbox" :checked="allSelected" @click="toggleSelectAll()"
                                    class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 cursor-pointer">
                            </th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">
                                No</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                Kode</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                Nama Sparepart</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                Jumlah</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                Diajukan Oleh</th>
                            <th
                                class="text-center px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-20">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in pengajuanList" :key="item.id">
                            <tr class="border-t border-slate-100 transition-colors cursor-pointer"
                                :class="isSelected(item.id) ? 'bg-blue-50/70' : 'hover:bg-slate-50'"
                                @click="toggleSelect(item.id)">
                                <td class="px-6 py-3.5 bg-white" @click.stop>
                                    <input type="checkbox" :checked="isSelected(item.id)" @click="toggleSelect(item.id)"
                                        class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 cursor-pointer">
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="text-sm text-slate-500" x-text="index + 1"></span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span
                                        class="font-mono text-xs text-blue-600 font-medium bg-blue-50 px-1.5 py-0.5 rounded"
                                        x-text="item.kode"></span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="text-sm font-medium text-slate-800" x-text="item.nama"></span>
                                </td>
                                <td class="px-6 py-3.5 text-center">
                                    <span class="text-sm font-semibold text-slate-700" x-text="item.quantity"></span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="text-sm text-slate-600" x-text="item.nama_user"></span>
                                </td>
                                <td class="px-6 py-3.5 text-center" @click.stop>
                                    <a :href="'daftar-sparepart/edit-daftar-sparepart.php?id=' + item.id"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50 transition-colors"
                                        title="Edit Kuantitas">
                                        <i class="ti ti-pencil text-base"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>

</main>

<?php include '../layouts/footer.php'; ?>