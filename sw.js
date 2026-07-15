// sw.js

// =========================================================================
// 1. IMPORT UTILITY DATABASE & ANTREAN
// =========================================================================
// Pastikan path relatif ini benar mengarah ke lokasi file js kamu dari posisi sw.js
importScripts('assets/js/db.js');
importScripts('assets/js/sync-queue.js');

const CACHE_NAME = 'buana-jaya-cache-v2';

// Daftar aset statis utama yang di-cache agar aplikasi bisa terbuka saat offline
const ASSETS_TO_CACHE = [
    './',
    './index.php',
    './assets/js/db.js',
    './assets/js/sync-queue.js',
    // Tambahkan file CSS, JS, atau ikon inti PWA kamu yang lain di bawah ini:
];

// =========================================================================
// 2. LIFECYCLE SERVICE WORKER (INSTALL & ACTIVATE)
// =========================================================================
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing New Version...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => self.skipWaiting()) // Memaksa SW baru langsung aktif
    );
});

self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating & Clearing Old Caches...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim()) // Langsung ambil kendali atas seluruh tab halaman aktif
    );
});

// =========================================================================
// 3. STRATEGI FETCH (NETWORK FIRST UNTUK PHP, CACHE FIRST UNTUK STATIC ASSETS)
// =========================================================================
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Abaikan request POST (seperti form submission atau sync endpoint) dari strategi caching
    if (event.request.method !== 'GET') {
        return;
    }

    // Strategi A: Network First untuk file dinamis (.php) atau halaman utama
    if (url.pathname.endsWith('.php') || url.pathname === '/') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Clone respons untuk disimpan di cache
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // Jika network gagal/offline, ambil dari cache lokal
                    return caches.match(event.request);
                })
        );
    } 
    // Strategi B: Cache First untuk file statis (CSS, JS, Images, Fonts)
    else {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request).then((response) => {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                });
            })
        );
    }
});

// =========================================================================
// 4. FITUR BACKGROUND SYNC (SINKRONISASI LATAR BELAKANG)
// =========================================================================
self.addEventListener('sync', (event) => {
    // Memeriksa kesesuaian tag sync yang dikirim dari form create-maintenance
    if (event.tag === 'sync-maintenance') {
        console.log('[Service Worker] Menangkap event sync-maintenance. Memulai sinkronisasi otomatis...');
        
        // event.waitUntil menjaga agar Service Worker tetap hidup sampai proses fungsi selesai
        event.waitUntil(
            syncMaintenanceQueue() // Fungsi dari assets/js/sync-queue.js
        );
    }
});