<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

$userId = $_SESSION['user_id'];

// Ambil sparepart yang stoknya tersedia di workshop user ini (stok > 0)
$sparepartQuery = mysqli_prepare($conn, "
    SELECT sw.sparepart_id, sw.stok,
           s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit,
           k.kode_komponen
    FROM stok_sparepart_wk sw
    JOIN sparepart s ON s.id = sw.sparepart_id
    LEFT JOIN komponen k ON k.id = s.komponen_id
    WHERE sw.user_id = ? AND sw.stok > 0
    ORDER BY s.nama_sparepart ASC
");
mysqli_stmt_bind_param($sparepartQuery, 'i', $userId);
mysqli_stmt_execute($sparepartQuery);
$sparepartResult = mysqli_stmt_get_result($sparepartQuery);

$sparepartList = [];
while ($row = mysqli_fetch_assoc($sparepartResult)) {

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
        'id'    => $row['sparepart_id'],
        'stok'  => (int) $row['stok'],
        'label' => $kodeGabungan . ' — ' . $namaLengkap . ' (Stok: ' . $row['stok'] . ')',
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
        rows: [{ rowId: 0, sparepartId: '', stok: null, query: '', quantity: '', showDropdown: false }],

        getFilteredList(row) {
            // Sembunyikan sparepart yang sudah dipilih di baris lain, supaya tidak double
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
            row.stok = item.stok;
            row.query = item.label;
            row.showDropdown = false;
        },

        addRow() {
            this.rowIdCounter++;
            this.rows.push({ rowId: this.rowIdCounter, sparepartId: '', stok: null, query: '', quantity: '', showDropdown: false });
        },

        removeRow(rowId) {
            if (this.rows.length > 1) {
                this.rows = this.rows.filter(r => r.rowId !== rowId);
            }
        },

        isExceeded(row) {
            return row.stok !== null && Number(row.quantity) > row.stok;
        },

        get hasAnyExceeded() {
            return this.rows.some(r => this.isExceeded(r));
        }
    }">

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
        <a href="<?= BASE_URL ?>/workshop/maintenance.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Workshop Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Catat Maintenance
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
                Informasi Maintenance
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                Catat penggunaan sparepart untuk maintenance unit, bisa lebih dari satu sparepart.
            </p>
        </div>

        <form action="store-maintenance.php" method="POST" class="p-6 space-y-5">

            <!-- Tipe Unit, Nopol, Mekanik -->
            <div class="grid sm:grid-cols-3 gap-5">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Tipe Unit
                    </label>
                    <div class="relative">
                        <i class="ti ti-truck absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            type="text"
                            name="type_unit"
                            placeholder="Excavator"
                            required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                   placeholder:text-slate-400 transition-all
                                   focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Nomor Polisi
                    </label>
                    <div class="relative">
                        <i class="ti ti-license absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            type="text"
                            name="nopol"
                            placeholder="KT 1234 AB"
                            required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm uppercase
                                   placeholder:text-slate-400 transition-all
                                   focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Nama Mekanik
                    </label>
                    <div class="relative">
                        <i class="ti ti-user-cog absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            type="text"
                            name="mekanik"
                            placeholder="Budi Santoso"
                            required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                   placeholder:text-slate-400 transition-all
                                   focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>
                </div>

            </div>

            <!-- Daftar Sparepart Digunakan -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Sparepart yang Digunakan
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
                                        @input="row.showDropdown = true; if (row.query === '') { row.sparepartId = ''; row.stok = null; }"
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

                                <!-- Info stok & warning -->
                                <p x-show="row.stok !== null" x-cloak class="text-xs mt-1.5" :class="isExceeded(row) ? 'text-red-500' : 'text-slate-400'">
                                    Stok tersedia: <span x-text="row.stok"></span> pcs
                                    <span x-show="isExceeded(row)"> — melebihi stok!</span>
                                </p>
                            </div>

                            <!-- Qty per baris -->
                            <div class="w-24">
                                <input
                                    type="number"
                                    :name="'items[' + index + '][quantity]'"
                                    x-model="row.quantity"
                                    placeholder="Qty"
                                    min="1"
                                    class="w-full px-3 py-2.5 rounded-lg border text-sm font-medium text-center
                                           placeholder:text-slate-400 placeholder:font-normal transition-all
                                           focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"
                                    :class="isExceeded(row) ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white'">
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

                <?php if (empty($sparepartList)): ?>
                <p class="text-xs text-red-500 mt-2">
                    Stok kamu kosong. Ajukan sparepart ke warehouse terlebih dahulu.
                </p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-4 border-t border-slate-200">
                <button
                    type="submit"
                    :disabled="hasAnyExceeded"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:translate-y-0">
                    <i class="ti ti-check text-base"></i>
                    Simpan Maintenance
                </button>
                <a href="<?= BASE_URL ?>/workshop/maintenance.php"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                    Batal
                </a>
            </div>

        </form>

    </div>

</main>

<?php include '../../layouts/footer.php'; ?>