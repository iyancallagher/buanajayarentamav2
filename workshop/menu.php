<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<a href="<?= BASE_URL ?>/workshop/index.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= ($currentPage === 'index.php')
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-dashboard text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Dashboard</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Dashboard
    </span>
</a>

<a href="<?= BASE_URL ?>/workshop/sparepart-stok.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'sparepart-stok.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-packages text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Stok Sparepart</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Stok Sparepart
    </span>
</a>

<div class="mt-6 mb-2 flex items-center justify-center pointer-events-none">
    <div x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="w-full px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
        Aktivitas
    </div>
    <div x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak class="w-6 border-t border-slate-700/50"></div>
</div>

<a href="<?= BASE_URL ?>/workshop/pengajuan-sparepart.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'pengajuan-sparepart.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-file-description text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Pengajuan Sparepart</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Pengajuan Sparepart
    </span>
</a>

<a href="<?= BASE_URL ?>/workshop/maintenance.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'maintenance.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-tools text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Maintenance</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Maintenance
    </span>
</a>

<div class="mt-6 mb-2 flex items-center justify-center pointer-events-none">
    <div x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="w-full px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
        Transaksi Barang
    </div>
    <div x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak class="w-6 border-t border-slate-700/50"></div>
</div>

<a href="<?= BASE_URL ?>/workshop/sparepart-masuk.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'sparepart-masuk.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-download text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Sparepart Masuk</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Sparepart Masuk
    </span>
</a>

<a href="<?= BASE_URL ?>/workshop/surat-pengiriman.php"
    class="flex items-center gap-3 px-4 py-3 mb-1 rounded-xl transition-all duration-200 relative group
        <?= $currentPage === 'surat-pengiriman.php'
            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 font-medium'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">

    <i class="ti ti-truck-delivery text-lg shrink-0"></i>
    
    <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">Pengiriman</span>

    <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen" x-cloak
        class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
        Pengiriman
    </span>
</a>