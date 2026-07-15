// CACHE_NAME: Ini adalah nama "laci penyimpanan" di memori browser Anda. 
// Jika Anda mengubah kodenya ke depan, Anda tinggal menaikkan versinya 
// (misal jadi v3) agar browser tahu ada pembaruan.

const CACHE_NAME = 'bjr-inventory-v2';

// assetsToCache: Ini adalah daftar file statis (CSS, JavaScript Chart, Alpine.js, dan Icon) yang wajib disimpan permanen. 
// File-file ini jarang berubah, jadi sangat aman disimpan di HP pengguna agar saat 
// membuka aplikasi, tampilannya langsung termuat instan tanpa perlu download ulang.
const assetsToCache = [
  '/buanajayarentama/assets/css/output.css?v=1',
  '/buanajayarentama/assets/css/tabler-icons/dist/tabler-icons.min.css?v=1',
  '/buanajayarentama/assets/js/chart.min.js?v=1',
  '/buanajayarentama/assets/js/alpine.min.js?v=1',
];

// Tahap Pemasangan Aplikasi
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(assetsToCache);
    })
  );
});

// Kode ini akan memeriksa semua laci cache lama yang tersimpan di HP user. Jika nama laci tersebut 
// tidak sama dengan CACHE_NAME yang aktif saat ini (bjr-inventory-v2), maka laci lama (seperti v1) 
// akan dihapus total untuk menghemat ruang penyimpanan HP.
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
});

// Strategi Cache: dipisah berdasarkan jenis file
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);


  // Khusus Halaman PHP ➔ Network First (Utamakan Internet)
  // Karena file PHP (seperti halaman monitoring stok gudang Anda sebelumnya) 
  // berisi data dinamis yang terus berubah di database, kodenya menggunakan strategi Network First:
  if (url.pathname.endsWith('.php')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Simpan salinan terbaru ke cache untuk fallback offline nanti
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
          return response;
        })
        .catch(() => {
          // Kalau offline/gagal fetch, baru pakai cache sebagai fallback
          return caches.match(event.request);
        })
    );
    return;
  }

  // Strategi B: Khusus File Statis ➔ Cache First (Utamakan Memori Lokal)
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      return cachedResponse || fetch(event.request);
    })
  );
});