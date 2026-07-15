<?php
/**
 * Component: Notifikasi Pengajuan Menunggu
 * -----------------------------------------
 * Hitung jumlah pengajuan sparepart berstatus 'draft' (belum diverifikasi).
 * Dipakai di navbar/sidebar untuk badge, dan di dashboard untuk banner.
 *
 * Cara pakai:
 *   require_once '../components/notif-pengajuan.php';
 *   $pengajuanMenunggu = getPengajuanMenunggu($conn);
 *
 * Lalu render badge di mana saja dengan:
 *   renderNotifBadge($pengajuanMenunggu);
 */

if (!function_exists('getPengajuanMenunggu')) {
    function getPengajuanMenunggu(mysqli $conn): int
    {
        $result = mysqli_query($conn, "
            SELECT COUNT(*) as total
            FROM pengajuan_sparepart
            WHERE status = 'draft'
        ");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }
}

if (!function_exists('renderNotifBadge')) {
    /**
     * Render badge angka kecil (dipakai di sidebar/navbar di samping menu/icon).
     * Tidak menampilkan apa pun jika $count == 0.
     */
    function renderNotifBadge(int $count): string
    {
        if ($count <= 0) return '';

        $display = $count > 99 ? '99+' : (string) $count;

        return '<span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none shrink-0">'
             . $display .
             '</span>';
    }

    /**
     * Render dot merah polos (tanpa angka) - alternatif lebih minimal,
     * cocok ditempel di pojok icon bell/menu tanpa mengubah layout.
     */
    function renderNotifDot(int $count): string
    {
        if ($count <= 0) return '';

        return '<span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>';
    }
}