// assets/js/db.js

const DB_NAME = 'buana_jaya_pwa_db';
const DB_VERSION = 1;
const STORE_NAME = 'maintenance_queue';

// Fungsi internal untuk membuka koneksi ke IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        // Terpanggil jika DB belum ada atau versi naik
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            // Buat object store (tabel lokal) dengan keyPath 'uuid'
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'uuid' });
            }
        };

        request.onsuccess = (event) => resolve(event.target.result);
        request.onerror = (event) => reject(event.target.error);
    });
}

// 1. Menyimpan atau memperbarui data maintenance di antrean offline
async function saveToQueue(maintenanceData) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME); // PERBAIKAN: dipastikan menggunakan STORE_NAME
        
        // Pastikan status default-nya pending saat pertama disimpan
        if (!maintenanceData.sync_status) {
            maintenanceData.sync_status = 'pending';
            maintenanceData.error_message = '';
        }

        const request = store.put(maintenanceData); // put() otomatis insert jika baru, atau update jika uuid sama

        request.onsuccess = () => resolve(true);
        request.onerror = (event) => reject(event.target.error);
    });
}

// 2. Mengambil semua data maintenance yang ada di antrean
async function getAllFromQueue() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.getAll();

        request.onsuccess = () => resolve(request.result);
        request.onerror = (event) => reject(event.target.error);
    });
}

// 3. Menghapus data dari antrean (dipakai setelah sukses sync ke MySQL)
async function deleteFromQueue(uuid) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.delete(uuid);

        request.onsuccess = () => resolve(true);
        request.onerror = (event) => reject(event.target.error);
    });
}

// 4. Menandai item jika terjadi kegagalan fatal (misal: Stok Tidak Cukup)
async function markAsConflict(uuid, errorMessage) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        
        // Ambil data aslinya dulu
        const getRequest = store.get(uuid);
        
        getRequest.onsuccess = () => {
            const data = getRequest.result;
            if (data) {
                data.sync_status = 'conflict';
                data.error_message = errorMessage;
                
                // Simpan kembali data yang sudah diubah statusnya
                const putRequest = store.put(data);
                putRequest.onsuccess = () => resolve(true);
                putRequest.onerror = (event) => reject(event.target.error);
            } else {
                resolve(false);
            }
        };
        
        transaction.onerror = (event) => reject(event.target.error);
    });
}