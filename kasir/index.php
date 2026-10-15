<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();

// Hitung cucian di setiap tahap antrean
$summary = [
    'baru' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Menunggu'")->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Diproses'")->fetchColumn(),
    'selesai' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Selesai'")->fetchColumn(),
    'unpaid' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_pembayaran = 'Belum Lunas'")->fetchColumn(),
];

// Ambil transaksi terbaru untuk antrean kasir
$recent = $pdo->query(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_cucian, t.status_pembayaran, u.nama AS nama_pelanggan
     FROM transaksi t 
     JOIN users u ON u.id = t.pelanggan_id 
     ORDER BY t.id DESC LIMIT 10'
)->fetchAll();

render_header('Dashboard Kasir');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-heading">Dashboard Kasir</h1>
        <p class="text-muted mb-0 small">Halo, <strong><?= e($_SESSION['user']['nama']) ?></strong>! Kelola antrean cucian dan transaksi pelanggan.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-primary" href="<?= e(app_url('kasir/buat_pesanan.php')) ?>">
            <i class="bi bi-plus-circle-fill"></i> Buat Pesanan Baru
        </a>
    </div>
</div>

<!-- Queue Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-amber h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon amber">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Cucian Menunggu</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= $summary['baru'] ?></div>
                    <div class="text-warning small fw-semibold" style="font-size: 0.72rem;">Belum mulai dicuci</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-sky h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Sedang Diproses</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= $summary['proses'] ?></div>
                    <div class="text-info small fw-semibold" style="font-size: 0.72rem;">Dalam proses pencucian</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-emerald h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Selesai Dicuci</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= $summary['selesai'] ?></div>
                    <div class="text-success small fw-semibold" style="font-size: 0.72rem;">Siap diambil pelanggan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-indigo h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon rose">
                    <i class="bi bi-credit-card"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Belum Lunas</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= $summary['unpaid'] ?></div>
                    <div class="text-danger small fw-semibold" style="font-size: 0.72rem;">Perlu ditagihkan</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shortcuts Bar -->
<div class="card bg-light border-0 p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 text-primary fw-bold small">
            <i class="bi bi-lightning-charge-fill"></i>
            <span>Menu Operasional Cepat:</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-primary" href="<?= e(app_url('kasir/buat_pesanan.php')) ?>">
                <i class="bi bi-plus-lg"></i> Terima Cucian
            </a>
            <a class="btn btn-sm btn-outline-success" href="<?= e(app_url('kasir/pembayaran.php')) ?>">
                <i class="bi bi-cash-stack"></i> Catat Pembayaran
            </a>
            <a class="btn btn-sm btn-outline-info" href="<?= e(app_url('kasir/update_status.php')) ?>">
                <i class="bi bi-arrow-repeat"></i> Status Cucian
            </a>
        </div>
    </div>
</div>

<!-- Recent Transactions Table -->
<div class="card shadow-sm">
    <div class="card-header-clean flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <h2 class="h6 fw-bold mb-0">Transaksi & Antrean Terkini</h2>
                <small class="text-muted">10 transaksi terbaru yang dicatat</small>
            </div>
        </div>
        <div style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari nota / pelanggan..." data-table-search="#kasirRecentTable">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="kasirRecentTable">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Pelanggan</th>
                    <th>Waktu Masuk</th>
                    <th>Biaya</th>
                    <th>Status Cucian</th>
                    <th>Pembayaran</th>
                    <th class="text-end">Aksi Kasir</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $item): ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($item['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="fw-semibold text-heading"><?= e($item['nama_pelanggan']) ?></div>
                        <small class="text-muted"><?= e($item['berat']) ?> kg</small>
                    </td>
                    <td>
                        <div class="text-heading small"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="fw-bold text-heading"><?= e(rupiah($item['total'])) ?></span>
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
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('kasir/pembayaran.php?id=' . $item['id'])) ?>" title="Catat Pembayaran">
                                <i class="bi bi-credit-card"></i> Bayar
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('kasir/update_status.php')) ?>" title="Update Status">
                                <i class="bi bi-arrow-repeat"></i> Status
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('kasir/nota.php?id=' . $item['id'])) ?>" title="Cetak Nota">
                                <i class="bi bi-printer"></i> Nota
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h6 class="fw-bold">Belum Ada Transaksi</h6>
                            <p class="text-muted small mb-0">Pesanan baru yang dibuat akan langsung tampil di sini.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
