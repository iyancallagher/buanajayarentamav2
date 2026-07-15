<?php
require_once '../config/database.php';
session_start();
$pageTitle = "Login - BJR Inventory";
// Include header template
include "../layouts/header.php"; // Sesuaikan jika letak file header.php Anda berbeda
?>

<div class="min-h-screen grid lg:grid-cols-2">

    <div class="flex flex-col justify-center px-6 sm:px-12 lg:px-20 py-12">
        <div class="max-w-md w-full mx-auto">

            <a href="../index.php" class="flex items-center gap-3 mb-10">
                <div
                    class="w-11 h-11 rounded-xl bg-gradient-to-br from-white-500 to-white-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <img src="<?= defined('BASE_URL') ? BASE_URL : '..' ?>/assets/img/logo192.png" alt="Logo BJR"
                        class="w-7 h-7 object-contain">
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight text-slate-800">BJR Inventory</h1>
                    <p class="text-[11px] text-slate-500 -mt-0.5">Sparepart Management</p>
                </div>
            </a>

            <div class="mb-8">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Selamat Datang</h2>
                <p class="text-slate-500">Masuk untuk mengakses sistem manajemen sparepart.</p>
            </div>

            <?php if (!empty($_SESSION['login_error'])): ?>
                <div
                    class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
                    <i class="ti ti-alert-circle text-base shrink-0"></i>
                    <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
                <?php unset($_SESSION['login_error']); endif; ?>

            <form action="proses_login.php" method="POST" class="space-y-5" x-data="{ showPassword: false }">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                    <div class="relative">
                        <i class="ti ti-mail absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="email" name="email"
                            value="<?= htmlspecialchars($_COOKIE['remember_email'] ?? '') ?>"
                            placeholder="nama@email.com" required
                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm placeholder:text-slate-400 transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                    <div class="relative">
                        <i class="ti ti-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input :type="showPassword ? 'text' : 'password'" name="password"
                            placeholder="Masukkan password" required
                            class="w-full pl-11 pr-11 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm placeholder:text-slate-400 transition-all focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="ti text-lg" :class="showPassword ? 'ti-eye-off' : 'ti-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember"
                            class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/30">
                        <span class="text-sm text-slate-600">Ingat saya</span>
                    </label>
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
                    Masuk <i class="ti ti-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="hidden lg:block relative overflow-hidden">
        <img src="../assets/img/gal9.png" alt="Gudang Sparepart" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/50 to-blue-900/30"></div>
        <div class="absolute top-20 right-20 w-72 h-72 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 left-10 w-72 h-72 bg-indigo-500/20 rounded-full blur-3xl"></div>
        <div class="absolute inset-0 flex flex-col justify-end p-12">
            <span
                class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-sm text-white text-xs font-semibold mb-6 w-fit border border-white/20">
                <i class="ti ti-sparkles text-sm"></i> PT. Buana Jaya Rentama
            </span>
            <h2 class="text-3xl font-bold text-white leading-tight mb-4">Kelola Sparepart secara
                Terpusat</h2>
            <p class="text-slate-200 mb-8 max-w-md">Satu sistem untuk Manajer, Workshop, dan warehouse.</p>
        </div>
    </div>

</div>

<?php
// Include footer template
include "../layouts/footer.php";
?>