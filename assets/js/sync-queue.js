// assets/js/sync-queue.js

// Fungsi utama untuk menyinkronkan seluruh antrean data dari IndexedDB ke MySQL
async function doSyncMaintenance() {
    // Pastikan fungsi ini bisa membaca database (getAllFromQueue, deleteFromQueue, markAsConflict harus sudah ada dari db.js)
    if (typeof getAllFromQueue !== 'function') {
        console.error('db.js belum di-load. Tidak dapat memproses sinkronisasi.');
        return;
    }

    try {
        const queue = await getAllFromQueue();
        
        // Filter hanya data yang berstatus 'pending' (data 'conflict' tidak di-retry otomatis)
        const pendingItems = queue.filter(item => item.sync_status === 'pending');

        if (pendingItems.length === 0) {
            console.log('Tidak ada antrean maintenance yang perlu disinkronkan.');
            return;
        }

        console.log(`Menemukan ${pendingItems.length} data maintenance offline. Memulai sinkronisasi...`);

        for (const item of pendingItems) {
            try {
                // Tembak data JSON ke endpoint sync di backend
                // Sesuaikan path jika dipanggil dari Service Worker (relative terhadap root/sw.js) 
                // atau dari halaman biasa (relative terhadap file PHP)
                const targetUrl = typeof window === 'undefined' 
                    ? './workshop/maintenance/store-maintenance-sync.php' 
                    : 'store-maintenance-sync.php';

                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Jika sukses ter-insert di MySQL server, hapus item dari IndexedDB
                    await deleteFromQueue(item.uuid);
                    console.log(`Data maintenance dengan UUID ${item.uuid} berhasil disinkronkan dan dihapus dari antrean lokal.`);
                } else if (response.status === 409 && result.stock_conflict) {
                    // Kasus bentrok stok fatal (stok tidak cukup saat disinkronkan)
                    // Tandai statusnya sebagai 'conflict' di IndexedDB agar tidak di-retry terus menerus
                    await markAsConflict(item.uuid, result.message || 'Stok tidak cukup');
                    console.warn(`Sinkronisasi tertunda untuk UUID ${item.uuid}: ${result.message}`);
                } else {
                    // Kegagalan respons server lainnya (misal error 500)
                    console.error(`Gagal menyinkronkan UUID ${item.uuid}:`, result.message);
                }
            } catch (fetchError) {
                // Jika jaringan tiba-tiba putus lagi di tengah jalan, hentikan loop
                // Biarkan sisa antrean diproses pada kesempatan online berikutnya
                console.error('Koneksi terputus saat proses sinkronisasi antrean:', fetchError);
                break;
            }
        }
    } catch (error) {
        console.error('Terjadi kesalahan pada IndexedDB saat melakukan sinkronisasi:', error);
    }
}

// =========================================================================
// Mendaftarkan Trigger Sinkronisasi (Hanya berjalan jika dipanggil di sisi client/window)
// =========================================================================
if (typeof window !== 'undefined') {
    // 1. Mendaftarkan melalui Background Sync API jika Service Worker aktif
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        navigator.serviceWorker.ready.then(async (registration) => {
            try {
                // Daftarkan tag sync bernama 'sync-maintenance'
                await registration.sync.register('sync-maintenance');
                console.log('Background Sync "sync-maintenance" berhasil didaftarkan.');
            } catch (err) {
                console.warn('Gagal mendaftarkan Background Sync, beralih ke fallback event online:', err);
            }
        });
    }

    // 2. Fallback Event Listener: Picu sinkronisasi manual saat browser mendeteksi perubahan sinyal ke 'online'
    window.addEventListener('online', () => {
        console.log('Sinyal kembali online! Memicu sinkronisasi antrean secara manual.');
        doSyncMaintenance();
    });
}