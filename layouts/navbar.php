<?php
// Pastikan session sudah di-start di file yang include navbar ini (biasanya di header.php)
$navUserName = $_SESSION['nama'] ?? 'Guest';
$navUserRole = $_SESSION['role'] ?? '-';

// Ambil huruf pertama dari nama untuk avatar (misal "Budi Workshop" -> "B")
$navUserInitial = strtoupper(substr($navUserName, 0, 1));
?>

<div
    x-data
    :class="$store.sidebar.collapsed ? 'lg:ml-20' : 'lg:ml-72'"
    class="flex flex-col min-h-screen">

    <header
        class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-6 sticky top-0 z-20">

        <!-- Left -->
        <div class="flex items-center gap-3 lg:gap-4">
            <button
                @click="$store.sidebar.toggle()"
                class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-100 transition-colors shrink-0">

                <i class="ti ti-menu-2 text-xl text-slate-700"></i>
            </button>
        </div>

        <!-- Right -->
        <div class="flex items-center gap-2 lg:gap-3">
            <!-- User -->
            <div class="relative" x-data="{ openProfile: false }">

                <button
                    @click="openProfile = !openProfile"
                    @click.outside="openProfile = false"
                    class="flex items-center gap-3 pl-3 lg:pl-4 ml-1 border-l border-slate-200 hover:bg-slate-50 rounded-xl py-1.5 pr-2 transition-colors">

                    <div class="text-right hidden sm:block">

                        <p class="font-semibold text-sm text-slate-800 leading-tight">
                            <?= htmlspecialchars($navUserName) ?>
                        </p>

                        <p class="text-xs text-slate-500 capitalize">
                            <?= htmlspecialchars($navUserRole) ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-bold shadow-sm shadow-blue-500/20 shrink-0">
                        <?= htmlspecialchars($navUserInitial) ?>
                    </div>

                    <i class="ti ti-chevron-down text-sm text-slate-400 hidden sm:block transition-transform" :class="openProfile ? 'rotate-180' : ''"></i>

                </button>

                <!-- Dropdown profile -->
                <div
                    x-show="openProfile"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">

                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="font-semibold text-sm text-slate-800">
                            <?= htmlspecialchars($navUserName) ?>
                        </p>
                        <p class="text-xs text-slate-500 capitalize mt-0.5">
                            <?= htmlspecialchars($navUserRole) ?>
                        </p>
                    </div>

                    <div class="border-t border-slate-100 mt-1 pt-1">
                        <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                            <i class="ti ti-logout text-base"></i>
                            Logout
                        </a>
                    </div>

                </div>

            </div>

        </div>

    </header>