<?php
/**
 * Component: Notifikasi Pengajuan Siap Dicetak (Kepala Gudang)
 * ---------------------------------------------------------------
 * Hitung jumlah pengajuan sparepart berstatus 'setuju' yang belum
 * dibuatkan surat jalan (surat_jalan_id IS NULL).
 */

if (!function_exists('getPengajuanSiapCetak')) {
    function getPengajuanSiapCetak(mysqli $conn): int
    {
        $result = mysqli_query($conn, "
            SELECT COUNT(*) as total
            FROM pengajuan_sparepart
            WHERE status = 'setuju' AND surat_jalan_id IS NULL
        ");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }
}