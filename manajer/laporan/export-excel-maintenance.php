<?php
// export-excel-maintenance.php

require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']); // Sesuaikan hak akses role ekspor

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$selectedMonth = $_GET['bulan'] ?? date('m');
$selectedYear  = $_GET['tahun'] ?? date('Y');
$searchValue   = $_GET['search'] ?? '';

// ===== 1. Kueri Utama Data Terfilter Tanpa LIMIT Pagination =====
$sql = "
    SELECT m.*, u.nama AS nama_workshop 
    FROM maintenance_wk m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE MONTH(m.created_at) = ? AND YEAR(m.created_at) = ?
";
$params = [$selectedMonth, $selectedYear];
$types  = 'ss';

if (!empty($searchValue)) {
    $sql .= " AND (m.type_unit LIKE ? OR m.nopol LIKE ? OR m.mekanik LIKE ? OR u.username LIKE ?)";
    $likeValue = '%' . $searchValue . '%';
    $params[] = $likeValue;
    $params[] = $likeValue;
    $params[] = $likeValue;
    $params[] = $likeValue;
    $types   .= 'ssss';
}

$sql .= " ORDER BY m.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ===== 2. Ambil Data Master Sparepart untuk Mapping ID -> Nama =====
$sparepartMaster = [];
$spQuery = mysqli_query($conn, "SELECT id, nama_sparepart FROM sparepart"); 
if ($spQuery) {
    while ($spRow = mysqli_fetch_assoc($spQuery)) {
        $sparepartMaster[$spRow['id']] = $spRow['nama_sparepart'];
    }
}

$namaBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// ==========================================
// PROSES INSTANSIASI PHP SPREADSHEET
// ==========================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Workshop Maintenance');
$sheet->setShowGridLines(true);

// Header Judul Dokumen
$sheet->setCellValue('A1', 'LAPORAN WORKSHOP MAINTENANCE & SUKU CADANG');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setName('Segoe UI')->setSize(15)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));

$subtitle = "Periode: " . ($namaBulan[$selectedMonth] ?? $selectedMonth) . " " . $selectedYear;
if (!empty($searchValue)) {
    $subtitle .= " | Kata Kunci: '" . $searchValue . "'";
}
$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getFont()->setName('Segoe UI')->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

// Header Tabel Utama
$headers = ["No", "Tanggal Masuk", "Workshop Pelapor", "Tipe Unit", "No. Polisi", "Mekanik", "Detail Suku Cadang Terpasang (Qty)"];
$sheet->fromArray($headers, NULL, 'A4');

$headerStyle = [
    'font' => ['name' => 'Segoe UI', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]
];
$sheet->getStyle('A4:G4')->applyFromArray($headerStyle);
$sheet->getRowDimension(4)->setRowHeight(26);

// ===== 3. Pengisian Data dan Parsing Objek JSON Sparepart =====
$rowNum = 5;
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $spareparts = json_decode($row['sparepart_list'], true) ?: [];
    
    // Bangun string teks gabungan sparepart agar pas di dalam satu sel row
    $partTextLines = [];
    foreach ($spareparts as $item) {
        $spId = $item['sparepart_id'] ?? null;
        $qty  = $item['quantity'] ?? $item['qty'] ?? 1;
        $namaPart = $sparepartMaster[$spId] ?? "ID: " . $spId;
        $partTextLines[] = "- " . $namaPart . " (" . $qty . " pcs)";
    }
    
    $detailSparepartString = (count($partTextLines) > 0) ? implode("\n", $partTextLines) : "Tidak ada penggantian sparepart";
    $namaWorkshop = $row['nama_workshop'] ? $row['nama_workshop'] : 'ID: ' . $row['user_id'];

    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, date('d-m-Y H:i', strtotime($row['created_at'])) . ' WITA');
    $sheet->setCellValue('C' . $rowNum, $namaWorkshop);
    $sheet->setCellValue('D' . $rowNum, $row['type_unit']);
    $sheet->setCellValue('E' . $rowNum, $row['nopol']);
    $sheet->setCellValue('F' . $rowNum, $row['mekanik']);
    $sheet->setCellValue('G' . $rowNum, $detailSparepartString);

    // Format Baris Data Excel
    $sheet->getStyle('G' . $rowNum)->getAlignment()->setWrapText(true); // Biarkan teks multi-line pecah ke bawah otomatis
    $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('G'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    
    $rowFillColor = ($rowNum % 2 == 0) ? 'F8FAFC' : 'FFFFFF';
    $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowFillColor);
    $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
    $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->getFont()->setName('Segoe UI')->setSize(9.5);
    
    $rowNum++;
}

// Lebarkan ukuran kolom otomatis agar tidak terpotong
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}
$sheet->getColumnDimension('G')->setWidth(45); // Set static width khusus untuk kolom deskripsi text JSON

// ===== 4. Pembersihan Buffer & Stream Output File Excel =====
if (ob_get_length()) {
    ob_end_clean();
}

$filename = "Laporan_Workshop_Maintenance_" . $selectedYear . $selectedMonth . "_" . date('His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;