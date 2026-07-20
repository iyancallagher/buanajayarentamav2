// assets/js/sync-queue.js

// Fungsi utama untuk memproses dan menguras antrean offline
async function syncMaintenanceQueue() {
    console.log('Memulai proses sinkronisasi antrean offline...');
    
    try {
        // 1. Ambil semua item dari IndexedDB (fungsi dari db.js)
        const queue = await getAllFromQueue();
        
        if (queue.length === 0) {
            console.log('Antrean kosong. Tidak ada data yang perlu disinkronkan.');
            return;
        }

        // Tentukan base path endpoint sync. 
        // Menggunakan absolute path agar aman dipanggil dari service worker maupun halaman workshop.
        const targetUrl = '/buanajayarentama/workshop/maintenance/store-maintenance-sync.php';

        // 2. Iterasi setiap data maintenance di dalam antrean
        for (const item of queue) {
            // Lewati item yang statusnya conflict (stok habis) agar tidak looping error terus
            if (item.sync_status === 'conflict') {
                console.log(`Item UUID ${item.uuid} dilewati karena status konflik stok.`);
                continue;
            }

            try {
                // Kirim data ke endpoint sync di server
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include', // KUNCI UTAMA: Menyertakan session login saat disinkronkan oleh Service Worker
                    body: JSON.stringify({
                        uuid: item.uuid,
                        type_unit: item.type_unit,
                        nopol: item.nopol,
                        mekanik: item.mekanik,
                        items: item.items,
                        created_at: item.created_at
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Skenario A: Sukses tersimpan di server MySQL
                    console.log(`UUID ${item.uuid} berhasil tersinkronisasi. Menghapus dari lokal...`);
                    await deleteFromQueue(item.uuid);
                } else if (response.status === 409 || (result && result.stock_conflict)) {
                    // Skenario B: Bentrok Stok (Gagal validasi server)
                    console.warn(`UUID ${item.uuid} gagal sync: Stok Tidak Cukup. Menandai konflik.`);
                    await markAsConflict(item.uuid, result.message || 'Stok tidak mencukupi saat sinkronisasi.');
                } else if (response.status === 401) {
                    // Skenario C: Sesi login habis
                    console.error('Sinkronisasi terhenti: Sesi pengguna telah berakhir (401).');
                    break; 
                } else {
                    // Gagal validasi lain atau server error
                    console.error(`UUID ${item.uuid} gagal diproses server:`, result.message);
                }
            } catch (fetchError) {
                // Sinyal drop kembali di tengah jalan saat proses looping sync
                console.error(`Gagal mengirim UUID ${item.uuid} karena gangguan jaringan:`, fetchError);
                break; // Hentikan loop, coba lagi di event sinkronisasi berikutnya
            }
        }
        
        console.log('Proses sinkronisasi antrean selesai dijalankan.');
    } catch (error) {
        console.error('Terjadi kesalahan sistem pada antrean IndexedDB:', error);
    }
}

// Mengaktifkan pemicu sinkronisasi manual saat browser mendeteksi status 'online'
if (typeof window !== 'undefined') {
    window.addEventListener('online', () => {
        console.log('Browser mendeteksi koneksi internet kembali. Memicu sync...');
        syncMaintenanceQueue();
    });
}