<?php
// export_excel.php

require_once '../../auth/auth_check.php';
requireRole(['manajer operasional']);

require_once '../../config/database.php';
// Load Composer autoloader untuk memanggil PhpSpreadsheet
require_once '../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Workbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$searchValue = $_GET['search'] ?? '';
$filterBulan = $_GET['bulan']  ?? '';

// ===== Susun boolean query untuk FULLTEXT search =====
$booleanQuery = '';
if (!empty($searchValue)) {
    $searchWords  = explode(' ', trim($searchValue));
    $searchWords  = array_filter($searchWords);
    $booleanQuery = implode(' ', array_map(fn($word) => '+' . $word . '*', $searchWords));
}

// ===== Helper bangun WHERE =====
function buildWhere(string $booleanQuery, string $bulan): array
{
    $where  = "WHERE 1=1";
    $params = [];
    $types  = '';

    if (!empty($booleanQuery)) {
        $where   .= " AND MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE)";
        $params[] = $booleanQuery;
        $types   .= 's';
    }

    if (!empty($bulan)) {
        $where   .= " AND DATE_FORMAT(k.created_at, '%Y-%m') = ?";
        $params[] = $bulan;
        $types   .= 's';
    }

    return [$where, $params, $types];
}

[$where, $params, $types] = buildWhere($booleanQuery, $filterBulan);
$relevanceSelect = "";
$orderByClause   = "ORDER BY k.created_at DESC";

if (!empty($booleanQuery)) {
    $relevanceSelect = ", MATCH(s.search_text) AGAINST (? IN BOOLEAN MODE) as relevance_score";
    $orderByClause   = "ORDER BY relevance_score DESC, k.created_at DESC";
    array_unshift($params, $booleanQuery);
    $types = 's' . $types;
}

// Ambil seluruh data tanpa LIMIT untuk export laporan
$stmt = mysqli_prepare($conn, "
    SELECT k.id, k.quantity, k.created_at,
           s.kode_sparepart AS kode,
           s.nama_sparepart AS nama,
           s.number_part,
           s.type_unit,
           kp.kode_komponen,
           u.nama AS nama_penerima
           $relevanceSelect
    FROM sparepart_keluar_wr k
    JOIN sparepart s ON s.id = k.sparepart_id
    LEFT JOIN komponen kp ON kp.id = s.komponen_id
    LEFT JOIN users u ON u.id = k.user_id
    $where
    $orderByClause
");

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ==========================================
// PROSES PEMBUATAN EXCEL DENGAN LIBRARY
// ==========================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sparepart Keluar');

// Aktifkan garis grid bawaan Excel
$sheet->setShowGridLines(true);

// 1. Judul Laporan
$sheet->setCellValue('A1', 'LAPORAN AKTIVITAS SPAREPART KELUAR (WAREHOUSE)');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setName('Segoe UI')->setSize(16)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));

$subtitle = "Filter: " . (!empty($searchValue) ? "Pencarian '$searchValue'" : "Semua Data");
if (!empty($filterBulan)) {
    $subtitle .= " | Bulan: " . date('F Y', strtotime($filterBulan . '-01'));
}
$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:F2');
$sheet->getStyle('A2')->getFont()->setName('Segoe UI')->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

// 2. Header Tabel
$headers = ["No", "Tanggal Keluar", "Kode Sparepart", "Nama & Deskripsi Sparepart", "Jumlah Keluar", "Dikirim Ke / Penerima"];
$sheet->fromArray($headers, NULL, 'A4');

// Styling Header Tabel (Slate Blue Theme)
$headerStyle = [
    'font' => ['name' => 'Segoe UI', 'size' => 11, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]
];
$sheet->getStyle('A4:F4')->applyFromArray($headerStyle);
$sheet->getRowDimension(4)->setRowHeight(28);

// 3. Looping Data dari Database
$rowNum = 5;
$no = 1;

while ($data = mysqli_fetch_assoc($result)) {
    $typeUnitArray   = json_decode($data['type_unit']   ?? '[]', true) ?: [];
    $numberPartArray = json_decode($data['number_part'] ?? '[]', true) ?: [];
    $typeUnitText    = implode(' / ', $typeUnitArray);
    $numberPartText  = implode('/', $numberPartArray);

    $namaLengkap = $data['nama'];
    if (!empty($typeUnitText))   $namaLengkap .= ' / ' . $typeUnitText;
    if (!empty($numberPartText)) $namaLengkap .= ' '   . $numberPartText;

    $kodeGabungan = !empty($data['kode_komponen']) ? $data['kode_komponen'] . '-' . $data['kode'] : $data['kode'];
    $penerimaText = !empty($data['nama_penerima']) ? $data['nama_penerima'] : 'Tidak diketahui';
    $qty = (int)$data['quantity'];

    // Tulis nilai ke cell
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, date('d M Y, H:i', strtotime($data['created_at'])));
    $sheet->setCellValue('C' . $rowNum, $kodeGabungan);
    $sheet->setCellValue('D' . $rowNum, $namaLengkap);
    $sheet->setCellValue('E' . $rowNum, -$qty); // Dibuat minus untuk indikasi barang keluar
    $sheet->setCellValue('F' . $rowNum, $penerimaText);

    // Format angka dan alignment data per baris
    $sheet->getStyle('A'.$rowNum.':C'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0');
    
    // Pewarnaan baris selang-seling (Zebra striping)
    $rowFillColor = ($rowNum % 2 == 0) ? 'F8FAFC' : 'FFFFFF';
    $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowFillColor);
    
    // Berikan border tipis pada data
    $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
    
    // Warna teks merah khusus untuk angka kuantitas barang keluar
    $sheet->getStyle('E'.$rowNum)->getFont()->setBold(true)->getColor()->setRGB('EF4444');
    $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getFont()->setName('Segoe UI')->setSize(10);
    
    $sheet->getRowDimension($rowNum)->setRowHeight(22);
    $rowNum++;
}

// 4. Baris Total Pengeluaran
$sheet->setCellValue('D' . $rowNum, 'Total Pengeluaran:');
$sheet->setCellValue('E' . $rowNum, "=SUM(E5:E" . ($rowNum - 1) . ")");

// Styling Total
$sheet->getStyle('D'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('E'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('D'.$rowNum.':E'.$rowNum)->getFont()->setName('Segoe UI')->setSize(11)->setBold(true);
$sheet->getStyle('E'.$rowNum)->getFont()->getColor()->setRGB('EF4444');

// Border Total (Garis atas tunggal, garis bawah ganda khas laporan akuntansi)
$totalBorderStyle = [
    'borders' => [
        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E293B']]
    ],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']] // Soft Blue Accent
];
$sheet->getStyle('D'.$rowNum.':E'.$rowNum)->applyFromArray($totalBorderStyle);
$sheet->getRowDimension($rowNum)->setRowHeight(24);

// 5. Otomatis Lebarkan Kolom Berdasarkan Isi Data
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

// 6. Proses Kirim File Excel Ke Browser Untuk Diunduh
$filename = "Laporan_Sparepart_Keluar_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // Stabilitas IE

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;