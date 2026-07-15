<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Bjr - Inventory' ?></title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/output.css?v=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tabler-icons/dist/tabler-icons.min.css?v=1">
    <script src="<?= BASE_URL ?>/assets/js/chart.min.js?v=1"></script>
    <script src="<?= BASE_URL ?>/assets/js/alpine.min.js?v=1" defer></script>
    <link rel="manifest" href="/buanajayarentama/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/buanajayarentama/sw.js')
                    .then(reg => console.log('PWA Service Worker Terdaftar!'))
                    .catch(err => console.error('PWA Gagal:', err));
            });
        }
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
                mobileOpen: false,

                toggle() {
                    // Di mobile, toggle overlay. Di desktop, toggle collapse.
                    if (window.innerWidth < 1024) {
                        this.mobileOpen = !this.mobileOpen;
                    } else {
                        this.collapsed = !this.collapsed;
                        localStorage.setItem('sidebar_collapsed', this.collapsed);
                    }
                },

                closeMobile() {
                    this.mobileOpen = false;
                }
            });
        });
    </script>
</head>

<body class="overflow-x-hidden bg-slate-100 font-sans" x-cloak>