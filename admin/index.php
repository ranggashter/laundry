<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');

// Ambil data ringkasan operasional dan keuangan dari database
$pdo = getPDO();
$statistics = [
    'transaksi' => (int) $pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn(),
    'pelanggan' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'")->fetchColumn(),
    'pendapatan' => (float) $pdo->query('SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran')->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian IN ('Menunggu', 'Diproses')")->fetchColumn(),
    'selesai' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Selesai'")->fetchColumn(),
];

// Ambil daftar transaksi terbaru untuk tabel ringkasan
$recentOrders = $pdo->query(
    "SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            u.nama AS nama_pelanggan
     FROM transaksi t
     JOIN users u ON u.id = t.pelanggan_id
     ORDER BY t.dibuat_pada DESC LIMIT 6"
)->fetchAll();

// Ambil ringkasan layanan aktif
$services = $pdo->query('SELECT id, nama, jenis, harga_per_kg, durasi_jam, aktif FROM layanan ORDER BY aktif DESC, jenis LIMIT 4')->fetchAll();

render_header('Dashboard Admin');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-heading">Dashboard Admin</h1>
        <p class="text-muted mb-0 small">Selamat datang kembali, <strong><?= e($_SESSION['user']['nama']) ?></strong>! Berikut rekapitulasi operasional laundry.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary" type="button" onclick="window.location.reload()" title="Perbarui data dashboard">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <a class="btn btn-outline-primary" href="<?= e(app_url('admin/laporan.php')) ?>">
            <i class="bi bi-bar-chart-line-fill"></i> Laporan Keuangan
        </a>
        <a class="btn btn-primary" href="<?= e(app_url('admin/layanan.php')) ?>">
            <i class="bi bi-plus-circle-fill"></i> Kelola Layanan
        </a>
    </div>
</div>

<!-- Stat Cards Grid -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-indigo h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon indigo">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Transaksi</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= number_format($statistics['transaksi']) ?></div>
                    <div class="text-muted small" style="font-size: 0.72rem;"><?= number_format($statistics['pelanggan']) ?> pelanggan terdaftar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-amber h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon amber">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Cucian Berjalan</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= number_format($statistics['proses']) ?></div>
                    <div class="text-warning small fw-semibold" style="font-size: 0.72rem;">Menunggu / Diproses</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-sky h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Cucian Selesai</div>
                    <div class="h3 mb-0 fw-bold text-heading"><?= number_format($statistics['selesai']) ?></div>
                    <div class="text-info small fw-semibold" style="font-size: 0.72rem;">Siap untuk diambil</div>
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
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($statistics['pendapatan'])) ?></div>
                    <div class="text-success small fw-semibold" style="font-size: 0.72rem;">Total pembayaran diterima</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Section: Recent Orders & Quick Management -->
<div class="row g-4">
    <!-- Recent Transactions Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Pesanan & Transaksi Terbaru</h2>
                        <small class="text-muted">Aktivitas pesanan terkini di semua kasir</small>
                    </div>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('admin/laporan.php')) ?>">
                    Semua Data <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nota</th>
                            <th>Pelanggan</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status Cucian</th>
                            <th>Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>
                                <span class="font-monospace fw-bold text-primary"><?= e($order['kode_nota']) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-heading"><?= e($order['nama_pelanggan']) ?></div>
                                <small class="text-muted"><?= e($order['berat']) ?> kg</small>
                            </td>
                            <td>
                                <div class="text-heading small"><?= e(date('d/m/Y', strtotime($order['dibuat_pada']))) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e(date('H:i', strtotime($order['dibuat_pada']))) ?> WIB</div>
                            </td>
                            <td>
                                <span class="fw-bold text-heading"><?= e(rupiah($order['total'])) ?></span>
                            </td>
                            <td>
                                <span class="badge-soft <?= e(status_badge_class($order['status_cucian'])) ?>">
                                    <i class="bi <?= e(status_icon($order['status_cucian'])) ?>"></i>
                                    <?= e($order['status_cucian']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-soft <?= e(status_badge_class($order['status_pembayaran'])) ?>">
                                    <?= e($order['status_pembayaran']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentOrders): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h6 class="fw-bold">Belum Ada Transaksi</h6>
                                    <p class="text-muted small mb-0">Pesanan yang dibuat oleh kasir akan otomatis muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Shortcuts & Services Preview -->
    <div class="col-lg-4">
        <div class="d-flex flex-column gap-4">
            <!-- Services Quick Card -->
            <div class="card shadow-sm">
                <div class="card-header-clean">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-box bg-info-subtle text-info">
                            <i class="bi bi-tags"></i>
                        </div>
                        <div>
                            <h2 class="h6 fw-bold mb-0">Tarif Layanan Aktif</h2>
                            <small class="text-muted">Paket pencucian saat ini</small>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin/layanan.php')) ?>">
                        Kelola
                    </a>
                </div>
                <div class="card-body p-3">
                    <div class="list-group list-group-flush">
                        <?php foreach ($services as $srv): ?>
                            <div class="list-group-item px-2 py-2 d-flex justify-content-between align-items-center border-0 border-bottom">
                                <div>
                                    <div class="fw-semibold text-heading small"><?= e($srv['nama']) ?></div>
                                    <small class="text-muted"><?= e($srv['durasi_jam']) ?> jam · <?= e($srv['jenis']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-primary small"><?= e(rupiah($srv['harga_per_kg'])) ?></span>
                                    <div class="text-muted" style="font-size: 0.68rem;">/kg</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Financial Report Callout -->
            <div class="card border-0 bg-primary text-white shadow-sm" style="background: var(--primary-gradient) !important;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.25rem;">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </div>
                        <span class="badge bg-white text-primary">Rekapitulasi</span>
                    </div>
                    <h3 class="h5 fw-bold text-white mb-1">Laporan Keuangan</h3>
                    <p class="text-white-50 small mb-3">Pantau arus kas masuk dan rincian transaksi berdasarkan filter tanggal.</p>
                    <a class="btn btn-light btn-sm text-primary fw-bold w-100" href="<?= e(app_url('admin/laporan.php')) ?>">
                        Buka Laporan Transaksi <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
