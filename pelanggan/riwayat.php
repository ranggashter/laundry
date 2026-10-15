<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');

// Ambil seluruh transaksi milik akun yang sedang masuk.
$query = getPDO()->prepare(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            d.nama_layanan
     FROM transaksi t LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? ORDER BY t.dibuat_pada DESC'
);
$query->execute([$_SESSION['user']['id']]);
$transactions = $query->fetchAll();

$totalWeight = array_sum(array_column($transactions, 'berat'));
$totalSpent = array_sum(array_column($transactions, 'total'));
$completedCount = count(array_filter($transactions, fn($t) => in_array($t['status_cucian'], ['Selesai', 'Diambil'])));

render_header('Riwayat Transaksi');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm">
            <i class="bi bi-clock-history"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Riwayat Transaksi Laundry</h1>
            <p class="text-muted mb-0 small">Daftar lengkap seluruh transaksi pencucian pakaian yang pernah Anda lakukan.</p>
        </div>
    </div>
    <span class="badge-soft badge-soft-primary px-3 py-2">
        <i class="bi bi-receipt me-1"></i> Total <?= count($transactions) ?> Transaksi
    </span>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="card stat-card stat-indigo h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon indigo">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Pesanan</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= count($transactions) ?></div>
                    <div class="text-muted small" style="font-size: 0.72rem;"><?= $completedCount ?> cucian selesai</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="card stat-card stat-sky h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Berat Dicuci</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= number_format($totalWeight, 1) ?> <span class="fs-6 fw-normal text-muted">kg</span></div>
                    <div class="text-info small fw-semibold" style="font-size: 0.72rem;">Akumulasi berat pakaian</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-md-4">
        <div class="card stat-card stat-emerald h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Pengeluaran</div>
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($totalSpent)) ?></div>
                    <div class="text-success small fw-semibold" style="font-size: 0.72rem;">Seluruh transaksi laundry</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Table Card -->
<div class="card shadow-sm">
    <div class="card-header-clean flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <h2 class="h6 fw-bold mb-0">Daftar Nota Cucian Anda</h2>
                <small class="text-muted">Diurutkan dari transaksi terbaru</small>
            </div>
        </div>
        <div style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari nota atau layanan..." data-table-search="#historyTable">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="historyTable">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Waktu Masuk</th>
                    <th>Paket Layanan</th>
                    <th>Berat</th>
                    <th>Total</th>
                    <th>Terbayar</th>
                    <th>Status Cucian</th>
                    <th>Pembayaran</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $item): ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($item['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="text-heading small"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="fw-semibold text-heading"><?= e($item['nama_layanan'] ?? '-') ?></span>
                    </td>
                    <td>
                        <span class="text-body"><?= e($item['berat']) ?> kg</span>
                    </td>
                    <td>
                        <span class="fw-bold text-heading"><?= e(rupiah($item['total'])) ?></span>
                    </td>
                    <td>
                        <span class="fw-semibold text-success"><?= e(rupiah($item['dibayar'])) ?></span>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($item['status_cucian'])) ?>">
                            <i class="bi <?= e(status_icon($item['status_cucian'])) ?>"></i>
                            <?= e($item['status_cucian']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($item['status_pembayaran'])) ?>">
                            <?= e($item['status_pembayaran']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('pelanggan/nota.php?id=' . $item['id'])) ?>">
                            <i class="bi bi-receipt"></i> Nota Digital
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h6 class="fw-bold">Belum Ada Riwayat Transaksi</h6>
                            <p class="text-muted small mb-0">Semua pesanan Anda di masa lalu akan tercatat lengkap di halaman ini.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
