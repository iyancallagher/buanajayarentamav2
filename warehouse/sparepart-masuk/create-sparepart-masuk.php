<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Ambil semua sparepart + komponen untuk dropdown, dengan format gabungan
$sparepartQuery = mysqli_query($conn, "
    SELECT s.id, s.kode_sparepart, s.nama_sparepart, s.number_part, s.type_unit, k.kode_komponen
    FROM sparepart s
    LEFT JOIN komponen k ON k.id = s.komponen_id
    ORDER BY s.nama_sparepart ASC
");

$sparepartList = [];
while ($row = mysqli_fetch_assoc($sparepartQuery)) {

    $typeUnitArray = json_decode($row['type_unit'] ?? '[]', true) ?: [];
    $numberPartArray = json_decode($row['number_part'] ?? '[]', true) ?: [];    

    $typeUnitText = implode(' / ', $typeUnitArray);
    $numberPartText = implode('/', $numberPartArray);

    $namaLengkap = $row['nama_sparepart'];

    if (!empty($typeUnitText)) {
        $namaLengkap .= ' / ' . $typeUnitText;
    }

    if (!empty($numberPartText)) {
        $namaLengkap .= '/ ' . $numberPartText;
    }

    $kodeGabungan = !empty($row['kode_komponen'])
        ? $row['kode_komponen'] . '-' . $row['kode_sparepart']
        : $row['kode_sparepart'];

    $sparepartList[] = [
        'id' => $row['id'],
        'kode' => $kodeGabungan,
        'label' => $kodeGabungan . ' — ' . $namaLengkap,
    ];
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>
<main class="p-6 bg-slate-50 min-h-screen" x-data="{
        sparepartList: <?= htmlspecialchars(json_encode($sparepartList), ENT_QUOTES) ?>,
        rowIdCounter: 1,
        rows: [{ rowId: 0, sparepartId: '', query: '', showDropdown: false }],

        getFilteredList(query) {
            if (query === '') return this.sparepartList;
            const q = query.toLowerCase();
            return this.sparepartList.filter(item => item.label.toLowerCase().includes(q));
        },

        selectItem(row, item) {
            row.sparepartId = item.id;
            row.query = item.label;
            row.showDropdown = false;
        },

        addRow() {
            this.rowIdCounter++;
            this.rows.push({ rowId: this.rowIdCounter, sparepartId: '', query: '', showDropdown: false });
        },

        removeRow(rowId) {
            if (this.rows.length > 1) {
                this.rows = this.rows.filter(r => r.rowId !== rowId);
            }
        }
    }">

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
        <a href="<?= BASE_URL ?>/warehouse/sparepart-masuk.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Warehouse Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Tambah Sparepart Masuk
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

    <div class="grid lg:grid-cols-1 gap-6 items-start">
        <!-- Form Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">Daftar Sparepart Masuk</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Catat satu atau beberapa sparepart yang baru diterima warehouse.
                </p>
            </div>

            <form action="store-sparepart-masuk.php" method="POST" class="p-6 space-y-4">

                <?php if (empty($sparepartList)): ?>
                    <p class="text-xs text-red-500 mb-2">
                        Belum ada data sparepart. Tambahkan sparepart di master data terlebih dahulu.
                    </p>
                <?php endif; ?>

                <template x-for="(row, index) in rows" :key="row.rowId">
                    <div class="flex gap-3 items-start p-4 rounded-xl border-2 transition-colors duration-200"
                        :class="row.sparepartId ? 'border-green-200 bg-green-50/40' : 'border-slate-200 bg-slate-50/50'">

                        <span
                            class="w-7 h-7 rounded-lg text-xs font-bold flex items-center justify-center shrink-0 mt-1 transition-colors duration-200"
                            :class="row.sparepartId ? 'bg-green-500 text-white' : 'bg-slate-300 text-slate-600'"
                            x-text="index + 1"></span>

                        <!-- Combobox sparepart per baris -->
                        <div class="relative flex-1" @click.outside="row.showDropdown = false">
                            <div class="relative">
                                <i
                                    class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                                <input type="text" x-model="row.query" @focus="row.showDropdown = true"
                                    @input="row.showDropdown = true; if (row.query === '') row.sparepartId = ''"
                                    placeholder="Cari kode atau nama sparepart..." autocomplete="off" class="w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm
                               placeholder:text-slate-400 transition-all
                               focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"
                                    :class="row.sparepartId ? 'border-green-300 bg-white' : 'border-slate-200 bg-white'">
                            </div>

                            <input type="hidden" :name="'items[' + index + '][sparepart_id]'" :value="row.sparepartId">

                            <div x-show="row.showDropdown" x-cloak x-transition.opacity.duration.100ms
                                class="absolute z-20 mt-2 w-full bg-white rounded-xl border border-slate-200 shadow-lg max-h-56 overflow-y-auto">

                                <template x-if="getFilteredList(row.query).length === 0">
                                    <p class="px-4 py-3 text-sm text-slate-400">Sparepart tidak ditemukan.</p>
                                </template>

                                <template x-for="item in getFilteredList(row.query)" :key="item.id">
                                    <button type="button" @click="selectItem(row, item)"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 transition-colors border-b border-slate-100 last:border-0"
                                        :class="row.sparepartId == item.id ? 'bg-blue-50 text-blue-600' : 'text-slate-700'">
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>

                            </div>
                        </div>

                        <!-- Jumlah per baris -->
                        <input type="number" :name="'items[' + index + '][quantity]'" placeholder="Qty" min="1" required class="w-24 px-3 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-center
                       placeholder:text-slate-400 placeholder:font-normal transition-all
                       focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">

                        <!-- Tombol hapus baris -->
                        <button type="button" @click="removeRow(row.rowId)" x-show="rows.length > 1"
                            class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-all duration-200 shrink-0 mt-0.5">
                            <i class="ti ti-trash text-base"></i>
                        </button>

                    </div>
                </template>

                <!-- Tombol tambah baris -->
                <button type="button" @click="addRow()"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/50 text-blue-600 text-sm font-semibold hover:border-blue-400 hover:bg-blue-100 hover:-translate-y-0.5 transition-all duration-200">
                    <i class="ti ti-plus text-base"></i>
                    Tambah Baris
                </button>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-200">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i>
                        Simpan
                        <span class="px-2 py-0.5 rounded-full bg-white/20 text-xs"
                            x-text="rows.length + ' item'"></span>
                    </button>
                    <a href="<?= BASE_URL ?>/warehouse/sparepart-masuk.php"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                        Batal
                    </a>
                </div>

            </form>
        </div>

        <!-- Info Panel -->
        <div class="bg-blue-50 rounded-2xl border border-blue-100 p-6">
            <div class="flex items-start gap-3">
                <i class="ti ti-info-circle text-xl text-blue-500 shrink-0 mt-0.5"></i>
                <div>
                    <h3 class="font-semibold text-blue-700 text-sm mb-1">
                        Stok Otomatis Bertambah
                    </h3>
                    <p class="text-xs text-blue-600 leading-relaxed">
                        Setelah disimpan, jumlah pada setiap baris akan otomatis ditambahkan ke stok sparepart warehouse
                        masing-masing. Semua baris disimpan dalam satu transaksi — jika salah satu gagal, semua dibatalkan.
                    </p>
                </div>
            </div>
        </div>

    </div>

</main>

<?php
unset($_SESSION['old_input']);
include '../../layouts/footer.php';
?>