<?php
require_once __DIR__ . '/../components/notif-pengajuan-gudang.php';
$pengajuanSiapCetak = getPengajuanSiapCetak($conn);

$currentPage = basename($_SERVER['PHP_SELF']);

// Definisikan halaman aktif untuk masing-masing grup menu dropdown
$transaksiPages     = ['sparepart-masuk.php', 'sparepart-keluar.php'];
$masterDataPages    = ['komponen.php', 'sparepart.php'];
$pengajuanPages      = ['daftar-sparepart.php', 'daftar-surat.php'];
$workshopPages    = ['workshop-stok.php', 'workshop-masuk.php', 'workshop-maintenance.php'];

$isTransaksiActive  = in_array($currentPage, $transaksiPages);
$isMasterDataActive = in_array($currentPage, $masterDataPages);
$isPengajuanActive  = in_array($currentPage, $pengajuanPages);
$isWorkshopActive  = in_array($currentPage, $workshopPages);
?>

<a href="<?= BASE_URL ?>/warehouse/index.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'index.php' || $currentPage === 'dashboard.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-dashboard text-lg shrink-0"></i>
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Dashboard</span>

    <span
        x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
        x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Dashboard
    </span>
</a>

<a href="<?= BASE_URL ?>/warehouse/sparepart-stok.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'sparepart-stok.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-package text-lg shrink-0"></i>
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Stok Sparepart</span>

    <span
        x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
        x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Stok Sparepart
    </span>
</a>

<div x-data="{ openTransaksi: <?= $isTransaksiActive ? 'true' : 'false' ?> }">

    <button
        @click.stop="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? null : (openTransaksi = !openTransaksi)"
        class="w-full flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
            <?= $isTransaksiActive
                ? 'bg-slate-800/60 text-white'
                : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

        <i class="ti ti-arrows-left-right text-lg shrink-0"></i>
        <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1 text-left">Transaksi Barang</span>

        <i
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="ti ti-chevron-down text-sm shrink-0 transition-transform duration-200"
            :class="openTransaksi ? 'rotate-180' : ''"></i>

        <span
            x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
            x-cloak
            class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
            Transaksi Barang
        </span>
    </button>

    <div
        x-show="openTransaksi && (!$store.sidebar.collapsed || $store.sidebar.mobileOpen)"
        x-cloak
        class="pl-4 space-y-1 mb-1">

        <a href="<?= BASE_URL ?>/warehouse/sparepart-masuk.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'sparepart-masuk.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-download text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Masuk</span>
        </a>

        <a href="<?= BASE_URL ?>/warehouse/sparepart-keluar.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'sparepart-keluar.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-truck-delivery text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Keluar</span>
        </a>
    </div>

</div>

<div x-data="{ openPengajuan: <?= $isPengajuanActive ? 'true' : 'false' ?> }">

    <button
        @click.stop="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? null : (openPengajuan = !openPengajuan)"
        class="w-full flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
            <?= $isPengajuanActive
                ? 'bg-slate-800/60 text-white'
                : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

        <i class="ti ti-clipboard-list text-lg shrink-0"></i>
        <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1 text-left">Pengajuan</span>

        <?php if ($pengajuanSiapCetak > 0): ?>
            <span
                x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
                x-transition.opacity
                class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none shrink-0">
                <?= $pengajuanSiapCetak > 99 ? '99+' : $pengajuanSiapCetak ?>
            </span>

            <span
                x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
                x-cloak
                class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full bg-red-500 ring-2 ring-slate-900"></span>
        <?php endif; ?>

        <i
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="ti ti-chevron-down text-sm shrink-0 transition-transform duration-200"
            :class="openPengajuan ? 'rotate-180' : ''"></i>

        <span
            x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
            x-cloak
            class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
            Pengajuan<?= $pengajuanSiapCetak > 0 ? " ({$pengajuanSiapCetak})" : '' ?>
        </span>
    </button>

    <div
        x-show="openPengajuan && (!$store.sidebar.collapsed || $store.sidebar.mobileOpen)"
        x-cloak
        class="pl-4 space-y-1 mb-1">

        <a href="<?= BASE_URL ?>/warehouse/daftar-sparepart.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'daftar-sparepart.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-file-text text-base shrink-0"></i>
            <span class="whitespace-nowrap flex-1">Daftar Sparepart</span>

            <?php if ($pengajuanSiapCetak > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full text-[10px] font-bold leading-none shrink-0
                    <?= $currentPage === 'daftar-sparepart.php' ? 'bg-white text-blue-600' : 'bg-red-500 text-white' ?>">
                    <?= $pengajuanSiapCetak > 99 ? '99+' : $pengajuanSiapCetak ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="<?= BASE_URL ?>/warehouse/daftar-surat.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'daftar-surat.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-notes text-base shrink-0"></i>
            <span class="whitespace-nowrap">Daftar Pengiriman</span>
        </a>
    </div>

</div>

<div x-data="{ openWorkshop: <?= $isWorkshopActive ? 'true' : 'false' ?> }">

    <button
        @click.stop="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? null : (openWorkshop = !openWorkshop)"
        class="w-full flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
            <?= $isWorkshopActive
                ? 'bg-slate-800/60 text-white'
                : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

        <i class="ti ti-tool text-lg shrink-0"></i>
        <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1 text-left">Workshop</span>

        <i
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="ti ti-chevron-down text-sm shrink-0 transition-transform duration-200"
            :class="openWorkshop ? 'rotate-180' : ''"></i>

        <span
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-cloak
            class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
            Workshop
        </span>
    </button>

    <div
        x-show="openWorkshop && (!$store.sidebar.collapsed || $store.sidebar.mobileOpen)"
        x-cloak
        class="pl-4 space-y-1 mb-1">

        <a href="<?= BASE_URL ?>/warehouse/workshop-stok.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-stok.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-package text-base shrink-0"></i>
            <span class="whitespace-nowrap">Stok Sparepart</span>
        </a>

        <a href="<?= BASE_URL ?>/warehouse/workshop-masuk.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-masuk.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-download text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Masuk</span>
        </a>

        <a href="<?= BASE_URL ?>/warehouse/workshop-maintenance.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-maintenance.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-settings text-base shrink-0"></i>
            <span class="whitespace-nowrap">Maintenance</span>
        </a>
    </div>
</div>


<div x-data="{ openMasterData: <?= $isMasterDataActive ? 'true' : 'false' ?> }">

    <button
        @click.stop="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? null : (openMasterData = !openMasterData)"
        class="w-full flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
            <?= $isMasterDataActive
                ? 'bg-slate-800/60 text-white'
                : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

        <i class="ti ti-database text-lg shrink-0"></i>
        <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1 text-left">Master Data</span>

        <i
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="ti ti-chevron-down text-sm shrink-0 transition-transform duration-200"
            :class="openMasterData ? 'rotate-180' : ''"></i>

        <span
            x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
            x-cloak
            class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
            Master Data
        </span>
    </button>

    <div
        x-show="openMasterData && (!$store.sidebar.collapsed || $store.sidebar.mobileOpen)"
        x-cloak
        class="pl-4 space-y-1 mb-1">

        <a href="<?= BASE_URL ?>/warehouse/komponen.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'komponen.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-settings text-base shrink-0"></i>
            <span class="whitespace-nowrap">Komponen</span>
        </a>

        <a href="<?= BASE_URL ?>/warehouse/sparepart.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'sparepart.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-components text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart</span>
        </a>
    </div>

</div>