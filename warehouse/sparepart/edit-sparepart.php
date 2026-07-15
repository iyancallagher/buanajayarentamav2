<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';

$id = $_GET['id'] ?? '';
$sql = "SELECT * FROM sparepart WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$sparepart = mysqli_fetch_assoc($result);

if (!$sparepart) {
    $_SESSION['form_error'] = 'Data sparepart tidak ditemukan!';
    header('Location: ../sparepart.php');
    exit;
}

// Bongkar data JSON dari DB untuk disuapkan ke array Alpine.js
$numberPartsArr = json_decode($sparepart['number_part'] ?? '[]', true) ?: [''];
$typeUnitsArr = json_decode($sparepart['type_unit'] ?? '[]', true) ?: [''];

$komponenQuery = "SELECT id, kode_komponen, nama_komponen FROM komponen ORDER BY nama_komponen ASC";
$komponenResult = mysqli_query($conn, $komponenQuery);
?>

<main class="p-6 bg-slate-50 min-h-screen">

    <div class="mb-6 flex items-center gap-3">
        <a href="../sparepart.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Warehouse panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Ubah Data Sparepart
            </h1>
        </div>
    </div>

    <?php if (!empty($_SESSION['form_error'])): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
            <i class="ti ti-alert-circle text-base shrink-0"></i>
            <?= htmlspecialchars($_SESSION['form_error']) ?>
        </div>
    <?php unset($_SESSION['form_error']); endif; ?>

    <div class="grid lg:grid-cols-1 gap-6 items-start" x-data="{
        numberParts: <?= htmlspecialchars(json_encode($numberPartsArr)) ?>,
        typeUnits: <?= htmlspecialchars(json_encode($typeUnitsArr)) ?>,
        addNumberPart() { this.numberParts.push('') },
        removeNumberPart(index) { if(this.numberParts.length > 1) this.numberParts.splice(index, 1) },
        addTypeUnit() { this.typeUnits.push('') },
        removeTypeUnit(index) { if(this.typeUnits.length > 1) this.typeUnits.splice(index, 1) }
    }">

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200">
            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">
                    Informasi Sparepart &mdash; <span class="text-blue-600"><?= htmlspecialchars($sparepart['kode_sparepart']) ?></span>
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                    Ubah data di bawah ini untuk memperbarui master data sparepart.
                </p>
            </div>

            <form action="update-sparepart.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="id" value="<?= $sparepart['id'] ?>">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Komponen Induk <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select name="komponen_id" required
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 appearance-none">
                            <?php while ($komponen = mysqli_fetch_assoc($komponenResult)): ?>
                                <option value="<?= $komponen['id'] ?>" <?= $sparepart['komponen_id'] == $komponen['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars('[' . $komponen['kode_komponen'] . '] ' . $komponen['nama_komponen']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i class="ti ti-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Nama Sparepart <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="ti ti-settings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="text" name="nama_sparepart"
                            value="<?= htmlspecialchars($sparepart['nama_sparepart']) ?>"
                            placeholder="Contoh: Oil Filter" required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Number Part <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-slate-400 mb-2">Gunakan tombol tambah jika item memiliki lebih dari satu nomor part.</p>
                    <div class="space-y-2">
                        <template x-for="(part, index) in numberParts" :key="index">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <i class="ti ti-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                                    <input type="text" name="number_part[]" x-model="numberParts[index]"
                                        placeholder="Contoh: 1234-XYZ"
                                        class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                                </div>
                                <button type="button" @click="removeNumberPart(index)" x-show="numberParts.length > 1"
                                    class="w-11 h-11 flex items-center justify-center shrink-0 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                                    <i class="ti ti-trash text-lg"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addNumberPart()"
                        class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                        <i class="ti ti-plus text-sm"></i> Tambah Number Part
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Type Unit <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-slate-400 mb-2">Tentukan kecocokan tipe unit mesin untuk sparepart ini.</p>
                    <div class="space-y-2">
                        <template x-for="(unit, index) in typeUnits" :key="index">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <i class="ti ti-truck absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                                    <input type="text" name="type_unit[]" x-model="typeUnits[index]"
                                        placeholder="Contoh: PC200-8"
                                        class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                                </div>
                                <button type="button" @click="removeTypeUnit(index)" x-show="typeUnits.length > 1"
                                    class="w-11 h-11 flex items-center justify-center shrink-0 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                                    <i class="ti ti-trash text-lg"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addTypeUnit()"
                        class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                        <i class="ti ti-plus text-sm"></i> Tambah Type Unit
                    </button>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i>
                        Simpan Perubahan
                    </button>
                    <a href="../sparepart.php"
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
                            Kode Sparepart Otomatis
                        </h3>
                        <p class="text-xs text-blue-600 leading-relaxed mb-3">
                            Sistem akan membuatkan urutan <code>kode_sparepart</code> secara otomatis berdasarkan 3 huruf pertama dari nama sparepart.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php
include '../../layouts/footer.php';
?>