<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Ambil semua kepala workshop untuk dropdown tujuan
$workshopQuery = mysqli_query($conn, "
    SELECT id, nama, site FROM users WHERE role = 'kepala workshop' ORDER BY nama ASC
");

$workshopList = [];
while ($row = mysqli_fetch_assoc($workshopQuery)) {
    $workshopList[] = $row;
}

// Ambil semua sparepart + komponen untuk dropdown, dengan format gabungan
$sparepartQuery = mysqli_query($conn, "
    SELECT s.id, s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit, k.kode_komponen
    FROM sparepart s
    LEFT JOIN komponen k ON k.id = s.komponen_id
    ORDER BY s.nama_sparepart ASC
");

$sparepartList = [];
while ($row = mysqli_fetch_assoc($sparepartQuery)) {

    $typeUnitArray   = json_decode($row['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($row['number_part'] ?? '[]', true) ?: [];

    $typeUnitText   = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = $row['nama_sparepart'];
    if (!empty($typeUnitText))   $namaLengkap .= ' / ' . $typeUnitText;
    if (!empty($numberPartText)) $namaLengkap .= '/ ' . $numberPartText;

    $kodeGabungan = !empty($row['kode_komponen'])
        ? $row['kode_komponen'] . '-' . $row['kode_sparepart']
        : $row['kode_sparepart'];

    $sparepartList[] = [
        'id'    => $row['id'],
        'label' => $kodeGabungan . ' — ' . $namaLengkap,
    ];
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>
<main
    class="p-6 bg-slate-50 min-h-screen"
    x-data="{
        sparepartList: <?= htmlspecialchars(json_encode($sparepartList), ENT_QUOTES) ?>,
        rowIdCounter: 1,
        rows: [{ rowId: 0, sparepartId: '', query: '', quantity: '', showDropdown: false }],

        getFilteredList(row) {
            const usedIds = this.rows.filter(r => r.rowId !== row.rowId).map(r => r.sparepartId).filter(id => id !== '');
            let list = this.sparepartList.filter(item => !usedIds.includes(item.id));

            if (row.query !== '') {
                const q = row.query.toLowerCase();
                list = list.filter(item => item.label.toLowerCase().includes(q));
            }
            return list;
        },

        selectItem(row, item) {
            row.sparepartId = item.id;
            row.query = item.label;
            row.showDropdown = false;
        },

        addRow() {
            this.rowIdCounter++;
            this.rows.push({ rowId: this.rowIdCounter, sparepartId: '', query: '', quantity: '', showDropdown: false });
        },

        removeRow(rowId) {
            if (this.rows.length > 1) {
                this.rows = this.rows.filter(r => r.rowId !== rowId);
            }
        }
    }">

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
        <a href="../daftar-surat.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Warehouse Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Buat Pengiriman
            </h1>
        </div>
    </div>

    <!-- Alert error -->
    <?php if (!empty($_SESSION['form_error'])): ?>
    <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
        <i class="ti ti-alert-circle text-base shrink-0"></i>
        <?= htmlspecialchars($_SESSION['form_error']) ?>
    </div>
    <?php unset($_SESSION['form_error']); endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

        <div class="p-6 border-b border-slate-200">
            <h2 class="font-semibold text-lg text-slate-800">
                Informasi Pengiriman
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                Pilih workshop tujuan dan sparepart yang akan dikirim.
            </p>
        </div>

        <form action="store-pengiriman.php" method="POST" class="p-6 space-y-5">

            <!-- Pilih Workshop Tujuan -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Workshop Tujuan
                </label>
                <div class="relative">
                    <i class="ti ti-building-warehouse absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none z-10"></i>
                    <select
                        name="user_id"
                        required
                        class="w-full pl-11 pr-10 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm appearance-none
                               focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        <option value="" disabled selected>Pilih workshop tujuan</option>
                        <?php foreach ($workshopList as $w): ?>
                        <option value="<?= htmlspecialchars($w['id']) ?>">
                            <?= htmlspecialchars($w['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="ti ti-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none"></i>
                </div>
                <?php if (empty($workshopList)): ?>
                <p class="text-xs text-red-500 mt-2">
                    Belum ada kepala workshop terdaftar.
                </p>
                <?php endif; ?>
            </div>

            <!-- Daftar Sparepart yang Dikirim -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Sparepart yang Dikirim
                </label>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="row.rowId">
                        <div class="flex gap-3 items-start p-4 rounded-xl border-2 transition-colors duration-200"
                            :class="row.sparepartId ? 'border-green-200 bg-green-50/40' : 'border-slate-200 bg-slate-50/50'">

                            <span class="w-7 h-7 rounded-lg text-xs font-bold flex items-center justify-center shrink-0 mt-1 transition-colors duration-200"
                                :class="row.sparepartId ? 'bg-green-500 text-white' : 'bg-slate-300 text-slate-600'"
                                x-text="index + 1"></span>

                            <!-- Combobox sparepart per baris -->
                            <div class="relative flex-1" @click.outside="row.showDropdown = false">
                                <div class="relative">
                                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                                    <input
                                        type="text"
                                        x-model="row.query"
                                        @focus="row.showDropdown = true"
                                        @input="row.showDropdown = true; if (row.query === '') row.sparepartId = ''"
                                        placeholder="Cari sparepart..."
                                        autocomplete="off"
                                        class="w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm
                                               placeholder:text-slate-400 transition-all
                                               focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"
                                        :class="row.sparepartId ? 'border-green-300 bg-white' : 'border-slate-200 bg-white'">
                                </div>

                                <input type="hidden" :name="'items[' + index + '][sparepart_id]'" :value="row.sparepartId">

                                <div x-show="row.showDropdown" x-cloak x-transition.opacity.duration.100ms
                                    class="absolute z-20 mt-2 w-full bg-white rounded-xl border border-slate-200 shadow-lg max-h-56 overflow-y-auto">

                                    <template x-if="getFilteredList(row).length === 0">
                                        <p class="px-4 py-3 text-sm text-slate-400">Sparepart tidak ditemukan.</p>
                                    </template>

                                    <template x-for="item in getFilteredList(row)" :key="item.id">
                                        <button type="button" @click="selectItem(row, item)"
                                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 transition-colors border-b border-slate-100 last:border-0"
                                            :class="row.sparepartId == item.id ? 'bg-blue-50 text-blue-600' : 'text-slate-700'">
                                            <span x-text="item.label"></span>
                                        </button>
                                    </template>

                                </div>
                            </div>

                            <!-- Qty per baris -->
                            <div class="w-24">
                                <input
                                    type="number"
                                    :name="'items[' + index + '][quantity]'"
                                    x-model="row.quantity"
                                    placeholder="Qty"
                                    min="1"
                                    class="w-full px-3 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-center
                                           placeholder:text-slate-400 placeholder:font-normal transition-all
                                           focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                            </div>

                            <!-- Tombol hapus baris -->
                            <button
                                type="button"
                                @click="removeRow(row.rowId)"
                                x-show="rows.length > 1"
                                class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-all duration-200 shrink-0 mt-0.5">
                                <i class="ti ti-trash text-base"></i>
                            </button>

                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    @click="addRow()"
                    class="w-full mt-3 flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/50 text-blue-600 text-sm font-semibold hover:border-blue-400 hover:bg-blue-100 hover:-translate-y-0.5 transition-all duration-200">
                    <i class="ti ti-plus text-base"></i>
                    Tambah Sparepart
                </button>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-4 border-t border-slate-200">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                    <i class="ti ti-check text-base"></i>
                    Buat Pengiriman
                </button>
                <a href="../daftar-surat.php"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                    Batal
                </a>
            </div>

        </form>

    </div>

</main>

<?php include '../../layouts/footer.php'; ?>