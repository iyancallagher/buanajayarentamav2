<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala workshop']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

// Ambil data site user yang sedang login
$userSite = $_SESSION['site'] ?? 'dalam kota'; // 'dalam kota' atau 'luar kota'
$wajibFoto = ($userSite === 'luar kota');

// Ambil semua sparepart untuk dropdown, dengan format gabungan
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
<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6 flex items-center gap-3">
        <a href="<?= BASE_URL ?>/workshop/pengajuan-sparepart.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Workshop Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Ajukan Sparepart
            </h1>
        </div>
    </div>

    <?php if (!empty($_SESSION['form_error'])): ?>
    <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2 max-w-2xl">
        <i class="ti ti-alert-circle text-base shrink-0"></i>
        <?= htmlspecialchars($_SESSION['form_error']) ?>
    </div>
    <?php unset($_SESSION['form_error']); endif; ?>

    <div class="grid lg:grid-cols-[2fr_1fr] gap-6 items-start">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">
                    Informasi Pengajuan
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                    Ajukan sparepart yang dibutuhkan ke warehouse.
                </p>
            </div>

            <form
                action="store-pengajuan.php"
                method="POST"
                enctype="multipart/form-data"
                class="p-6 space-y-5"
                x-data="{
                    sparepartList: <?= htmlspecialchars(json_encode($sparepartList), ENT_QUOTES) ?>,
                    query: '',
                    selectedId: '',
                    showDropdown: false,
                    wajibFoto: <?= $wajibFoto ? 'true' : 'false' ?>,
                    fotoFiles: [],
                    fotoError: '',

                    get filteredList() {
                        if (this.query === '') return this.sparepartList;
                        const q = this.query.toLowerCase();
                        return this.sparepartList.filter(item => item.label.toLowerCase().includes(q));
                    },

                    selectItem(item) {
                        this.selectedId = item.id;
                        this.query = item.label;
                        this.showDropdown = false;
                    },

                    handleFotoChange(event) {
                        const newFiles = Array.from(event.target.files);

                        if (this.fotoFiles.length + newFiles.length > 3) {
                            this.fotoError = 'Maksimal 3 foto.';
                            event.target.value = '';
                            return;
                        }

                        this.fotoError = '';
                        this.fotoFiles = [...this.fotoFiles, ...newFiles];
                        this.syncFileInput();
                    },

                    removeFoto(index) {
                        this.fotoFiles.splice(index, 1);
                        this.syncFileInput();
                    },

                    syncFileInput() {
                        const dataTransfer = new DataTransfer();
                        this.fotoFiles.forEach(file => dataTransfer.items.add(file));
                        this.$refs.fotoInput.files = dataTransfer.files;
                    }
                }">

                <div class="relative" @click.outside="showDropdown = false">
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Sparepart
                    </label>

                    <div class="relative">
                        <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            type="text"
                            x-model="query"
                            @focus="showDropdown = true"
                            @input="showDropdown = true; if (query === '') selectedId = ''"
                            placeholder="Cari kode atau nama sparepart..."
                            autocomplete="off"
                            class="w-full pl-11 pr-10 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                   placeholder:text-slate-400 transition-all
                                   focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        <i class="ti ti-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-base transition-transform" :class="showDropdown ? 'rotate-180' : ''"></i>
                    </div>

                    <input type="hidden" name="sparepart_id" :value="selectedId" required>

                    <div
                        x-show="showDropdown"
                        x-cloak
                        x-transition.opacity.duration.100ms
                        class="absolute z-20 mt-2 w-full bg-white rounded-xl border border-slate-200 shadow-lg max-h-64 overflow-y-auto">

                        <template x-if="filteredList.length === 0">
                            <p class="px-4 py-3 text-sm text-slate-400">Sparepart tidak ditemukan.</p>
                        </template>

                        <template x-for="item in filteredList" :key="item.id">
                            <button type="button" @click="selectItem(item)"
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 transition-colors border-b border-slate-100 last:border-0"
                                :class="selectedId == item.id ? 'bg-blue-50 text-blue-600' : 'text-slate-700'">
                                <span x-text="item.label"></span>
                            </button>
                        </template>

                    </div>

                    <?php if (empty($sparepartList)): ?>
                    <p class="text-xs text-red-500 mt-2">
                        Belum ada data sparepart. Hubungi warehouse untuk menambahkan master data.
                    </p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Kondisi / Alasan Pengajuan
                    </label>
                    <textarea
                        name="kondisi_sparepart"
                        rows="4"
                        placeholder="Jelaskan kondisi sparepart yang rusak/habis dan alasan pengajuan..."
                        required
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm resize-none
                               placeholder:text-slate-400 transition-all
                               focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"></textarea>
                </div>

                <div x-show="wajibFoto" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Foto Sparepart
                        <span x-show="wajibFoto" class="text-red-500">*</span>
                    </label>

                    <label
                        x-show="fotoFiles.length < 3"
                        class="flex items-center justify-center gap-2 w-full py-4 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 text-sm font-medium cursor-pointer hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50/50 transition-all duration-200">
                        <i class="ti ti-camera text-lg"></i>
                        <span>Ambil Foto dengan Kamera</span>
                        
                        <input
                            type="file"
                            name="foto[]"
                            x-ref="fotoInput"
                            accept="image/*"
                            capture="environment"
                            multiple
                            @change="handleFotoChange($event)"
                            class="hidden">
                    </label>

                    <p x-show="fotoError" class="text-xs text-red-500 mt-2" x-text="fotoError"></p>

                    <div x-show="fotoFiles.length > 0" class="flex flex-wrap gap-4 mt-4">
                        <template x-for="(file, index) in fotoFiles" :key="index">
                            <div class="relative w-24 h-24 rounded-xl border border-slate-200 bg-slate-100 shadow-sm shrink-0">
                                <img :src="URL.createObjectURL(file)" class="w-full h-full object-cover rounded-xl">
                                
                                <button
                                    type="button"
                                    @click="removeFoto(index)"
                                    class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center border-2 border-white shadow-md hover:bg-red-600 active:scale-90 transition-all z-10"
                                    title="Batalkan foto">
                                    <i class="ti ti-x text-[12px] font-bold"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        <span x-text="fotoFiles.length"></span>/3 foto · Minimal 1 foto wajib diambil langsung dari kamera (tidak bisa unggah dari galeri).
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="submit"
                        @click="if (wajibFoto && fotoFiles.length === 0) { fotoError = 'Foto wajib diisi minimal 1 untuk user luar kota.'; $event.preventDefault(); }"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i>
                        Ajukan Sparepart
                    </button>
                    <a href="<?= BASE_URL ?>/workshop/pengajuan-sparepart.php"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                        Batal
                    </a>
                </div>

            </form>

        </div>

        <div class="space-y-4">

            <div class="bg-blue-50 rounded-2xl border border-blue-100 p-6">
                <div class="flex items-start gap-3">
                    <i class="ti ti-info-circle text-xl text-blue-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="font-semibold text-blue-700 text-sm mb-1">
                            Status Lokasi Kamu: <?= htmlspecialchars(ucwords($userSite)) ?>
                        </h3>
                        <p class="text-xs text-blue-600 leading-relaxed">
                            <?php if ($wajibFoto): ?>
                            Sebagai workshop luar kota, kamu wajib mengunggah minimal 1 foto sparepart sebagai bukti kondisi fisik barang.
                            <?php else: ?>
                            Sebagai workshop dalam kota, foto bersifat opsional karena tim warehouse dapat melakukan pengecekan langsung.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div x-show="wajibFoto" x-cloak class="bg-amber-50 rounded-2xl border border-amber-100 p-6">
                <div class="flex items-start gap-3">
                    <i class="ti ti-camera text-xl text-amber-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="font-semibold text-amber-700 text-sm mb-1">
                            Foto Harus dari Kamera
                        </h3>
                        <p class="text-xs text-amber-600 leading-relaxed">
                            Foto tidak bisa diambil dari galeri/album, harus difoto langsung saat ini juga untuk memastikan keaslian kondisi sparepart.
                        </p>
                    </div>
                </div>
            </div>

        </div>

    </div>

</main>

<?php include '../../layouts/footer.php'; ?>