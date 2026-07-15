<aside x-data :class="{
        'w-20': $store.sidebar.collapsed && !$store.sidebar.mobileOpen,
        'w-72': !$store.sidebar.collapsed || $store.sidebar.mobileOpen,
        'translate-x-0': $store.sidebar.mobileOpen,
        '-translate-x-full': !$store.sidebar.mobileOpen
    }"
    class="fixed top-0 left-0 h-screen bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 text-white flex flex-col shadow-2xl border-r border-slate-800/60 z-40 overflow-x-clip lg:translate-x-0">

    <!-- Accent glow line -->
    <div class="absolute top-0 right-0 h-full w-px bg-gradient-to-b from-transparent via-blue-500/30 to-transparent">
    </div>

    <!-- Logo -->
    <div class="h-20 flex items-center gap-3 px-6 border-b border-slate-800/60 relative overflow-hidden shrink-0">
        <div
            class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
            <i class="ti ti-package text-white text-lg"></i>
        </div>
        <div x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity
            class="whitespace-nowrap">
            <h1 class="font-bold text-lg leading-tight tracking-tight">
                BJR Inventory
            </h1>
            <p class="text-[11px] text-slate-400 font-medium">
                Sparepart Management
            </p>
        </div>

        <button @click="$store.sidebar.closeMobile()"
            class="absolute right-4 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-800 lg:hidden">
            <i class="ti ti-x text-lg text-slate-400"></i>
        </button>
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-4 py-5 overflow-y-auto overflow-x-clip sidebar-scroll"
        @click="if (window.innerWidth < 1024) $store.sidebar.closeMobile()">

        <?php include $menuFile; ?>

    </nav>

    <!-- Logout -->
    <div class="border-t border-slate-800/60 p-4 shrink-0">
        <a href="<?= BASE_URL ?>/auth/logout.php"
            class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400/90 hover:text-red-300 hover:bg-red-500/10 transition-all duration-200 group relative">

            <i class="ti ti-logout text-lg group-hover:translate-x-0.5 transition-transform shrink-0"></i>

            <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" x-transition.opacity
                class="font-medium text-sm whitespace-nowrap">Logout</span>

            <span x-show="$store.sidebar.collapsed && !$store.sidebar.mobileOpen"
                class="absolute left-full ml-3 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                Logout
            </span>

        </a>
    </div>

</aside>

<style>
    .sidebar-scroll::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.4);
        border-radius: 10px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, 0.6);
    }
</style>