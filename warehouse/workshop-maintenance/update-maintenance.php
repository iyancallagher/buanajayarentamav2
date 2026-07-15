<?php
require_once '../../auth/auth_check.php';
requireRole(['kepala gudang']);
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenanceId = (int)$_POST['maintenance_id'];
    $typeUnit      = trim($_POST['type_unit']);
    $nopol         = trim($_POST['nopol']);
    $mekanik       = trim($_POST['mekanik']);
    $inputParts    = $_POST['parts'] ?? [];

    if ($maintenanceId <= 0 || empty($typeUnit) || empty($nopol) || empty($mekanik)) {
        $_SESSION['form_error'] = "Seluruh kolom identitas unit wajib diisi.";
        header("Location: ../workshop-maintenance.php");
        exit;
    }

    // Memulai Transaction database demi keamanan relasional stok
    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil list data pemakaian suku cadang lama sebelum di-update
        $sqlFetch = "SELECT sparepart_list, user_id FROM maintenance_wk WHERE id = ? FOR UPDATE";
        $stmtFetch = mysqli_prepare($conn, $sqlFetch);
        mysqli_stmt_bind_param($stmtFetch, "i", $maintenanceId);
        mysqli_stmt_execute($stmtFetch);
        $mRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFetch));

        if (!$mRow) {
            throw new Exception("Data pekerjaan maintenance tidak ditemukan.");
        }

        $userId = (int)$mRow['user_id'];
        $oldPartsList = json_decode($mRow['sparepart_list'], true) ?: [];

        // Petakan Qty Lama ke dalam array asosiatif [sparepart_id => qty_lama]
        $oldQtyMapping = [];
        foreach ($oldPartsList as $op) {
            $spId = (int)($op['sparepart_id'] ?? 0);
            $q    = (int)($op['quantity'] ?? $op['qty'] ?? 1);
            if ($spId > 0) $oldQtyMapping[$spId] = $q;
        }

        // 2. Susun Array JSON baru sekaligus hitung selisih perubahan stok
        $newPartsList = [];
        
        foreach ($inputParts as $ip) {
            $sparepartId = (int)$ip['sparepart_id'];
            $qtyBaru     = (int)$ip['qty'];

            if ($sparepartId <= 0 || $qtyBaru <= 0) {
                throw new Exception("Format kuantitas suku cadang tidak valid.");
            }

            // Daftarkan ke struktur skema JSON baru
            $newPartsList[] = [
                'sparepart_id' => $sparepartId,
                'quantity'     => $qtyBaru
            ];

            // Hitung selisih: Karena ini data maintenance (pengurangan stok),
            // Rumus: Selisih Mutasi Stok = Qty Lama - Qty Baru
            $qtyLama = $oldQtyMapping[$sparepartId] ?? 0;
            $selisihStok = $qtyLama - $qtyBaru;

            if ($selisihStok != 0) {
                // Update tabel stok_sparepart_wk secara dinamis
                $sqlUpdateStok = "UPDATE stok_sparepart_wk SET stok = stok + ? WHERE sparepart_id = ? AND user_id = ?";
                $stmtUpdateStok = mysqli_prepare($conn, $sqlUpdateStok);
                mysqli_stmt_bind_param($stmtUpdateStok, "iii", $selisihStok, $sparepartId, $userId);
                mysqli_stmt_execute($stmtUpdateStok);
            }
        }

        // Encode kembali list data baru ke string JSON
        $newJsonString = json_encode($newPartsList);

        // 3. Update data utama tabel maintenance_wk
        $sqlUpdateMain = "UPDATE maintenance_wk SET type_unit = ?, nopol = ?, mekanik = ?, sparepart_list = ? WHERE id = ?";
        $stmtUpdateMain = mysqli_prepare($conn, $sqlUpdateMain);
        mysqli_stmt_bind_param($stmtUpdateMain, "ssssi", $typeUnit, $nopol, $mekanik, $newJsonString, $maintenanceId);
        mysqli_stmt_execute($stmtUpdateMain);

        // Commit perubahan
        mysqli_commit($conn);
        $_SESSION['form_success'] = "Laporan maintenance unit " . htmlspecialchars($nopol) . " berhasil dikoreksi.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['form_error'] = "Gagal memproses update data: " . $e->getMessage();
    }
}

header("Location: ../workshop-maintenance.php");
exit;