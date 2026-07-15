<?php

require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>
<main class="p-6 bg-slate-50 min-h-screen">

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
        <a href="../komponen.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Warehouse Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Tambah Komponen Baru
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

    <!-- Grid 2 kolom: Form (kiri, lebih lebar) + Info Panel (kanan) -->
    <div class="grid lg:grid-cols-2 gap-6 items-start">

        <!-- Form Card -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">
                    Informasi Komponen
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                    Lengkapi data di bawah untuk menambahkan komponen baru ke sistem.
                </p>
            </div>

            <form action="store-komponen.php" method="POST" class="p-6 space-y-5" x-data="{ showPassword: false }">
                <!-- Nama & Email sejajar di desktop -->
                <div class="grid sm:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Nama Komponen
                        </label>
                        <div class="relative">
                            <i class="ti ti-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="nama"
                                value="<?= htmlspecialchars($_SESSION['old_input']['nama'] ?? '') ?>"
                                placeholder="Contoh: Engine" required
                                class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                       placeholder:text-slate-400 transition-all
                                       focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        </div>
                    </div>
                    <div class="mt-4">
                    <div class="flex items-center gap-3 pt-4 border-slate-200">
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                            <i class="ti ti-check text-base"></i>
                            Simpan Komponen
                        </button>
                        <a href="<?= BASE_URL ?>warehouse/komponen.php"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                            Batal
                        </a>
                    </div>
                </div>
                </div>
        </div>

        <!-- Info Panel (kanan) -->
        <div class="space-y-4">

            <div class="bg-blue-50 rounded-2xl border border-blue-100 p-6">
                <div class="flex items-start gap-3">
                    <i class="ti ti-shield-lock text-xl text-blue-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="font-semibold text-blue-700 text-sm mb-1">
                            Kode Komponen
                        </h3>
                        <p class="text-xs text-blue-600 leading-relaxed">
                            kode komponen akan otomatis dibuat berdasarkan nama komponen yang kamu masukkan, namun pastikan nama komponen yang kamu buat unik dan tidak sama dengan komponen lain yang sudah ada di sistem.
                        </p>
                    </div>
                </div>
            </div>

        </div>

    </div>

</main>

<?php
unset($_SESSION['old_input']);
include '../../layouts/footer.php';
?>