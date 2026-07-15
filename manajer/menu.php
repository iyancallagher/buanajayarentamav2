<?php
require_once __DIR__ . '/../components/notif-pengajuan.php';
$pengajuanMenunggu = getPengajuanMenunggu($conn);

$currentPage = basename($_SERVER['PHP_SELF']);

// Definisikan halaman aktif untuk dropdown sub-menu
$warehousePages   = ['warehouse-stok.php', 'warehouse-masuk.php', 'warehouse-keluar.php'];
$workshopPages    = ['workshop-stok.php', 'workshop-masuk.php', 'workshop-maintenance.php'];

$isWarehouseActive = in_array($currentPage, $warehousePages);
$isWorkshopActive  = in_array($currentPage, $workshopPages);
?>

<a href="<?= BASE_URL ?>/manajer/index.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'index.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-dashboard text-lg shrink-0"></i>
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Dashboard</span>

    <span
        x-show="$store.sidebar.collapsed"
        x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Dashboard
    </span>
</a>

<a href="<?= BASE_URL ?>/manajer/pengajuan-sparepart.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'pengajuan-sparepart.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-file-description text-lg shrink-0"></i>
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1">Pengajuan Sparepart</span>

    <?php if ($pengajuanMenunggu > 0): ?>
        <span
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-bold leading-none shrink-0
                <?= $currentPage === 'pengajuan-sparepart.php' ? 'bg-white text-blue-600' : 'bg-red-500 text-white' ?>">
            <?= $pengajuanMenunggu > 99 ? '99+' : $pengajuanMenunggu ?>
        </span>

        <span
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-cloak
            class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full bg-red-500 ring-2 ring-slate-900"></span>
    <?php endif; ?>

    <span
        x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
        x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Pengajuan Sparepart<?= $pengajuanMenunggu > 0 ? " ({$pengajuanMenunggu})" : '' ?>
    </span>
</a>

<div x-data="{ openWarehouse: <?= $isWarehouseActive ? 'true' : 'false' ?> }">

    <button
        @click.stop="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? null : (openWarehouse = !openWarehouse)"
        class="w-full flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
            <?= $isWarehouseActive
                ? 'bg-slate-800/60 text-white'
                : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

        <i class="ti ti-building-warehouse text-lg shrink-0"></i>
        <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap flex-1 text-left">Warehouse</span>

        <i
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-transition.opacity
            class="ti ti-chevron-down text-sm shrink-0 transition-transform duration-200"
            :class="openWarehouse ? 'rotate-180' : ''"></i>

        <span
            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
            x-cloak
            class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
            Warehouse
        </span>
    </button>

    <div
        x-show="openWarehouse && (!$store.sidebar.collapsed || $store.sidebar.mobileOpen)"
        x-cloak
        class="pl-4 space-y-1 mb-1">

        <a href="<?= BASE_URL ?>/manajer/warehouse-stok.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'warehouse-stok.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-package text-base shrink-0"></i>
            <span class="whitespace-nowrap">Stok Sparepart</span>
        </a>

        <a href="<?= BASE_URL ?>/manajer/warehouse-masuk.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'warehouse-masuk.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-download text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Masuk</span>
        </a>

        <a href="<?= BASE_URL ?>/manajer/warehouse-keluar.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'warehouse-keluar.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-truck-delivery text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Keluar</span>
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

        <a href="<?= BASE_URL ?>/manajer/workshop-stok.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-stok.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-package text-base shrink-0"></i>
            <span class="whitespace-nowrap">Stok Sparepart</span>
        </a>

        <a href="<?= BASE_URL ?>/manajer/workshop-masuk.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-masuk.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-download text-base shrink-0"></i>
            <span class="whitespace-nowrap">Sparepart Masuk</span>
        </a>

        <a href="<?= BASE_URL ?>/manajer/workshop-maintenance.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200
                <?= $currentPage === 'workshop-maintenance.php'
                    ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
            <i class="ti ti-settings text-base shrink-0"></i>
            <span class="whitespace-nowrap">Maintenance</span>
        </a>
    </div>
</div>

<a href="<?= BASE_URL ?>/manajer/user.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'user.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-user text-lg shrink-0"></i>
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Data User</span>

    <span
        x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
        x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        User
    </span>
</a>