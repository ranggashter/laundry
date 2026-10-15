<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();

// Filter memakai rentang tanggal inklusif dan parameter terpisah untuk mencegah SQL injection.
$from = $_GET['dari'] ?? date('Y-m-01');
$to = $_GET['sampai'] ?? date('Y-m-d');
$validDate = static function ($value): bool {
    $date = DateTime::createFromFormat('Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === $value;
};
if (!$validDate($from) || !$validDate($to) || $from > $to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}

// Ambil transaksi hanya dalam rentang tanggal yang diminta.
$statement = $pdo->prepare(
    "SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            pelanggan.nama AS nama_pelanggan, kasir.nama AS nama_kasir
     FROM transaksi t
     JOIN users pelanggan ON pelanggan.id = t.pelanggan_id
     JOIN users kasir ON kasir.id = t.kasir_id
     WHERE t.dibuat_pada >= ? AND t.dibuat_pada < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY t.dibuat_pada DESC"
);
$statement->execute([$from, $to]);
$transactions = $statement->fetchAll();

$gross = array_sum(array_column($transactions, 'total'));
$totalWeight = array_sum(array_column($transactions, 'berat'));

// Pembayaran dihitung dari tanggal uang diterima, bukan tanggal pesanan dibuat.
$paymentTotal = $pdo->prepare(
    'SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran
     WHERE dibayar_pada >= ? AND dibayar_pada < DATE_ADD(?, INTERVAL 1 DAY)'
);
$paymentTotal->execute([$from, $to]);
$collected = (float) $paymentTotal->fetchColumn();
$uncollected = max(0, $gross - $collected);

if (($_GET['export'] ?? '') === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Transaksi');
    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', 'LAPORAN TRANSAKSI & KEUANGAN');
    $sheet->mergeCells('A2:I2');
    $sheet->setCellValue('A2', 'Periode: ' . date('d/m/Y', strtotime($from)) . ' s/d ' . date('d/m/Y', strtotime($to)));

    $summary = [
        ['Jumlah transaksi', count($transactions)],
        ['Total berat (kg)', $totalWeight],
        ['Nilai tagihan', $gross],
        ['Kas masuk', $collected],
        ['Belum terbayar', $uncollected],
    ];
    foreach ($summary as $index => [$label, $value]) {
        $row = $index + 4;
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
    }

    $headers = ['Kode Nota', 'Waktu Masuk', 'Pelanggan', 'Kasir', 'Berat (kg)', 'Total', 'Dibayar', 'Status Cucian', 'Pembayaran'];
    foreach ($headers as $index => $header) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $sheet->setCellValue($column . '10', $header);
    }

    $textColumn = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
    foreach ($transactions as $index => $transaction) {
        $row = $index + 11;
        $sheet->setCellValueExplicit("A{$row}", (string) $transaction['kode_nota'], $textColumn);
        $sheet->setCellValue("B{$row}", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTimeImmutable($transaction['dibuat_pada'])));
        $sheet->setCellValueExplicit("C{$row}", (string) $transaction['nama_pelanggan'], $textColumn);
        $sheet->setCellValueExplicit("D{$row}", (string) $transaction['nama_kasir'], $textColumn);
        $sheet->setCellValue("E{$row}", (float) $transaction['berat']);
        $sheet->setCellValue("F{$row}", (float) $transaction['total']);
        $sheet->setCellValue("G{$row}", (float) $transaction['dibayar']);
        $sheet->setCellValueExplicit("H{$row}", (string) $transaction['status_cucian'], $textColumn);
        $sheet->setCellValueExplicit("I{$row}", (string) $transaction['status_pembayaran'], $textColumn);
    }

    $lastRow = max(10, count($transactions) + 10);
    $sheet->getStyle('A1:I1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 16],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF176B65']],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->getStyle('A2:I2')->getFont()->setItalic(true)->getColor()->setARGB('FF52616B');
    $sheet->getStyle('A4:A8')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FF344054']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4F3']],
    ]);
    $sheet->getStyle('B4:B8')->getFont()->setBold(true);
    $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('#,##0.0" kg"');
    $sheet->getStyle('B6:B8')->getNumberFormat()->setFormatCode('"Rp" #,##0');
    $sheet->getStyle('A10:I10')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF176B65']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getRowDimension(10)->setRowHeight(30);
    if ($transactions) {
        $table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A10:I' . $lastRow, 'LaporanTransaksi');
        $tableStyle = new \PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle();
        $tableStyle->setTheme('TableStyleMedium4')->setShowRowStripes(true);
        $table->setStyle($tableStyle);
        $sheet->addTable($table);
        $sheet->getStyle('B11:B' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle('E11:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0" kg"');
        $sheet->getStyle('F11:G' . $lastRow)->getNumberFormat()->setFormatCode('"Rp" #,##0');
    } else {
        $sheet->getAutoFilter()->setRange('A10:I10');
    }

    foreach (['A' => 19, 'B' => 21, 'C' => 28, 'D' => 24, 'E' => 15, 'F' => 19, 'G' => 19, 'H' => 20, 'I' => 20] as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }
    $sheet->freezePane('A11');
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(10, 10);
    $sheet->getPageSetup()->setPrintArea('A1:I' . $lastRow);

    $filename = sprintf('laporan-laundry-%s-%s.xlsx', $from, $to);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

render_header('Laporan Transaksi');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon sky shadow-sm">
            <i class="bi bi-bar-chart-line-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Laporan Transaksi & Keuangan</h1>
            <p class="text-muted mb-0 small">Rekapitulasi riwayat pesanan laundry dan arus kas masuk.</p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 no-print">
        <a class="btn btn-outline-success" href="<?= e(app_url('admin/laporan.php?' . http_build_query(['dari' => $from, 'sampai' => $to, 'export' => 'excel']))) ?>">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Ekspor Excel
        </a>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Cetak Laporan
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body p-4">
        <form class="row gy-3 gx-3 align-items-end" method="get">
            <div class="col-md-4 col-sm-6">
                <label class="form-label" for="dari">
                    <i class="bi bi-calendar-event text-muted"></i> Dari Tanggal
                </label>
                <input class="form-control" type="date" id="dari" name="dari" value="<?= e($from) ?>" required>
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="form-label" for="sampai">
                    <i class="bi bi-calendar-event text-muted"></i> Sampai Tanggal
                </label>
                <input class="form-control" type="date" id="sampai" name="sampai" value="<?= e($to) ?>" required>
            </div>
            <div class="col-md-4 col-sm-12 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">
                    <i class="bi bi-funnel-fill"></i> Filter Data
                </button>
                <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/laporan.php?dari=' . date('Y-m-01') . '&sampai=' . date('Y-m-d'))) ?>" title="Reset Bulan Ini">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-indigo h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon indigo">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Jumlah Transaksi</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= count($transactions) ?></div>
                    <div class="text-muted small" style="font-size: 0.72rem;">Total berat: <?= number_format($totalWeight, 1) ?> kg</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-sky h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Nilai Tagihan</div>
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($gross)) ?></div>
                    <div class="text-info small fw-semibold" style="font-size: 0.72rem;">Total nilai kotor</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-emerald h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Kas Masuk</div>
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($collected)) ?></div>
                    <div class="text-success small fw-semibold" style="font-size: 0.72rem;">Sudah dibayar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-amber h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon amber">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Belum Terbayar</div>
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($uncollected)) ?></div>
                    <div class="text-warning small fw-semibold" style="font-size: 0.72rem;">Sisa piutang</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm">
    <div class="card-header-clean flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary">
                <i class="bi bi-table"></i>
            </div>
            <div>
                <h2 class="h6 fw-bold mb-0">Rincian Transaksi</h2>
                <small class="text-muted">Periode: <?= date('d M Y', strtotime($from)) ?> s/d <?= date('d M Y', strtotime($to)) ?></small>
            </div>
        </div>
        <div class="no-print" style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari nota atau pelanggan..." data-table-search="#reportTable">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="reportTable">
            <thead>
                <tr>
                    <th>Kode Nota</th>
                    <th>Waktu Masuk</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th>Berat</th>
                    <th>Total</th>
                    <th>Dibayar</th>
                    <th>Status Cucian</th>
                    <th>Pembayaran</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($transaction['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="text-heading small"><?= e(date('d/m/Y', strtotime($transaction['dibuat_pada']))) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(date('H:i', strtotime($transaction['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="fw-semibold text-heading"><?= e($transaction['nama_pelanggan']) ?></span>
                    </td>
                    <td>
                        <span class="text-muted small"><?= e($transaction['nama_kasir']) ?></span>
                    </td>
                    <td>
                        <span class="fw-semibold"><?= e($transaction['berat']) ?> kg</span>
                    </td>
                    <td>
                        <span class="fw-bold text-heading"><?= e(rupiah($transaction['total'])) ?></span>
                    </td>
                    <td>
                        <span class="fw-semibold text-success"><?= e(rupiah($transaction['dibayar'])) ?></span>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($transaction['status_cucian'])) ?>">
                            <i class="bi <?= e(status_icon($transaction['status_cucian'])) ?>"></i>
                            <?= e($transaction['status_cucian']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($transaction['status_pembayaran'])) ?>">
                            <?= e($transaction['status_pembayaran']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h6 class="fw-bold">Tidak Ada Transaksi</h6>
                            <p class="text-muted small mb-0">Tidak ada riwayat transaksi pada rentang tanggal yang dipilih.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            <?php if ($transactions): ?>
            <tfoot class="table-light">
                <tr class="fw-bold text-heading">
                    <td colspan="4" class="text-end">Total Ringkasan:</td>
                    <td><?= number_format($totalWeight, 1) ?> kg</td>
                    <td><?= e(rupiah($gross)) ?></td>
                    <td class="text-success"><?= e(rupiah($collected)) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php render_footer(); ?>
