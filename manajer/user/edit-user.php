<?php

require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../../config/database.php';

$menuFile = __DIR__ . '/../menu.php';

$userIdToEdit = $_GET['id'] ?? null;

if (!$userIdToEdit) {
    header('Location: ../user.php');
    exit;
}

// Ambil data user yang akan diedit
$stmt = mysqli_prepare($conn, "SELECT id, nama, email, role, site FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $userIdToEdit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result);

if (!$userData) {
    header('Location: ../user.php');
    exit;
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
include '../../layouts/navbar.php';
?>
<main class="p-6 bg-slate-50 min-h-screen">

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
        <a href="<?= BASE_URL ?>/manajer/user.php"
            class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
            <i class="ti ti-arrow-left text-lg text-slate-600"></i>
        </a>
        <div>
            <p class="text-xs font-medium text-blue-600 mb-1 tracking-wide uppercase">
                Manajer Panel
            </p>
            <h1 class="text-2xl font-bold text-slate-800">
                Edit User
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

    <div class="grid lg:grid-cols-[2fr_1fr] gap-6 items-start">

        <!-- Form Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">

            <div class="p-6 border-b border-slate-200">
                <h2 class="font-semibold text-lg text-slate-800">
                    Informasi User
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                    Perbarui data user di bawah ini.
                </p>
            </div>

            <form action="update-user.php" method="POST" class="p-6 space-y-5">

                <input type="hidden" name="id" value="<?= htmlspecialchars($userData['id']) ?>">

                <div class="grid sm:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Nama Lengkap
                        </label>
                        <div class="relative">
                            <i class="ti ti-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input
                                type="text"
                                name="nama"
                                value="<?= htmlspecialchars($userData['nama']) ?>"
                                required
                                class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                       focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Email
                        </label>
                        <div class="relative">
                            <i class="ti ti-mail absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input
                                type="email"
                                name="email"
                                value="<?= htmlspecialchars($userData['email']) ?>"
                                required
                                class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                       focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        </div>
                    </div>

                </div>

                <!-- Password baru, opsional -->
                <div x-data="{ showPassword: false }">
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Password Baru <span class="text-slate-400 font-normal">(kosongkan jika tidak diubah)</span>
                    </label>
                    <div class="relative">
                        <i class="ti ti-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            placeholder="Minimal 8 karakter"
                            minlength="8"
                            class="w-full pl-11 pr-11 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm
                                   placeholder:text-slate-400
                                   focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="ti text-lg" :class="showPassword ? 'ti-eye-off' : 'ti-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Role
                        </label>
                        <div class="relative">
                            <i class="ti ti-shield-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none z-10"></i>
                            <select
                                name="role"
                                required
                                class="w-full pl-11 pr-10 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm appearance-none
                                       focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                                <option value="manajer operasional" <?= $userData['role'] === 'manajer operasional' ? 'selected' : '' ?>>Manajer Operasional</option>
                                <option value="kepala gudang" <?= $userData['role'] === 'kepala gudang' ? 'selected' : '' ?>>Kepala Gudang</option>
                                <option value="kepala workshop" <?= $userData['role'] === 'kepala workshop' ? 'selected' : '' ?>>Kepala Workshop</option>
                            </select>
                            <i class="ti ti-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Site
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="relative flex items-center justify-center gap-1.5 px-3 py-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-slate-600
                                [&:has(input:checked)]:border-blue-500 [&:has(input:checked)]:bg-blue-50 [&:has(input:checked)]:text-blue-600">
                                <input type="radio" name="site" value="dalam kota" required class="sr-only" <?= $userData['site'] === 'dalam kota' ? 'checked' : '' ?>>
                                <i class="ti ti-building text-base"></i>
                                <span class="text-sm font-medium">Dalam Kota</span>
                            </label>
                            <label class="relative flex items-center justify-center gap-1.5 px-3 py-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-slate-600
                                [&:has(input:checked)]:border-blue-500 [&:has(input:checked)]:bg-blue-50 [&:has(input:checked)]:text-blue-600">
                                <input type="radio" name="site" value="luar kota" required class="sr-only" <?= $userData['site'] === 'luar kota' ? 'checked' : '' ?>>
                                <i class="ti ti-map-pin text-base"></i>
                                <span class="text-sm font-medium">Luar Kota</span>
                            </label>
                        </div>
                    </div>

                </div>

                <div class="flex items-center gap-3 pt-4 border-slate-200">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="ti ti-check text-base"></i>
                        Simpan Perubahan
                    </button>
                    <a href="../user.php"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-all duration-200">
                        Batal
                    </a>
                </div>

            </form>

        </div>

        <!-- Info Panel -->
        <div class="bg-blue-50 rounded-2xl border border-blue-100 p-6">
            <div class="flex items-start gap-3">
                <i class="ti ti-shield-lock text-xl text-blue-500 shrink-0 mt-0.5"></i>
                <div>
                    <h3 class="font-semibold text-blue-700 text-sm mb-1">
                        Mengubah Password
                    </h3>
                    <p class="text-xs text-blue-600 leading-relaxed">
                        Biarkan kolom password kosong jika tidak ingin mengubahnya. Isi hanya jika user perlu password baru.
                    </p>
                </div>
            </div>
        </div>

    </div>

</main>

<?php include '../../layouts/footer.php'; ?>