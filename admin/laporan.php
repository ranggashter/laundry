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
// Pembayaran dihitung dari tanggal uang diterima, bukan tanggal pesanan dibuat.
$paymentTotal = $pdo->prepare(
    'SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran
     WHERE dibayar_pada >= ? AND dibayar_pada < DATE_ADD(?, INTERVAL 1 DAY)'
);
$paymentTotal->execute([$from, $to]);
$collected = (float) $paymentTotal->fetchColumn();
render_header('Laporan Transaksi');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon sky shadow-sm">
            <i class="bi bi-bar-chart-line-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Laporan Transaksi</h1>
            <p class="text-muted mb-0 small">Rekapitulasi riwayat pesanan laundry dan arus kas masuk.</p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-soft badge-soft-info px-3 py-2">
            <i class="bi bi-calendar-range me-1"></i> <?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?>
        </span>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <form class="row gy-3 gx-3 align-items-end" method="get">
            <div class="col-sm-4">
                <label class="form-label" for="dari">
                    <i class="bi bi-calendar-event text-muted"></i> Dari Tanggal
                </label>
                <input class="form-control" type="date" id="dari" name="dari" value="<?= e($from) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="sampai">
                    <i class="bi bi-calendar-event text-muted"></i> Sampai Tanggal
                </label>
                <input class="form-control" type="date" id="sampai" name="sampai" value="<?= e($to) ?>" required>
            </div>
            <div class="col-sm-4">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-filter"></i> Tampilkan Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon indigo">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Jumlah Transaksi</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= count($transactions) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-calculator"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Nilai Total Transaksi</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= e(rupiah($gross)) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Pembayaran Diterima</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= e(rupiah($collected)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <h2 class="h6 fw-bold mb-0">Rincian Transaksi</h2>
        </div>
        <span class="text-muted small"><?= count($transactions) ?> baris data</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode Nota</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th>Berat</th>
                    <th>Total</th>
                    <th>Dibayar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($transaction['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="text-heading"><?= e(date('d/m/Y', strtotime($transaction['dibuat_pada']))) ?></div>
                        <div class="text-muted small"><?= e(date('H:i', strtotime($transaction['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-heading"><?= e($transaction['nama_pelanggan']) ?></div>
                    </td>
                    <td>
                        <span class="text-muted"><?= e($transaction['nama_kasir']) ?></span>
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
                        <span class="badge-soft <?= e(status_badge_class($transaction['status_pembayaran'])) ?>">
                            <?= e($transaction['status_pembayaran']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr>
                    <td colspan="8">
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
        </table>
    </div>
</div>
<?php render_footer(); ?>
