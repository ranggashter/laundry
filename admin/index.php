<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');

// Ambil angka ringkasan yang akan ditampilkan pada kartu dashboard.
$pdo = getPDO();
$statistics = [
    'transaksi' => (int) $pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn(),
    'pelanggan' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'")->fetchColumn(),
    'pendapatan' => (float) $pdo->query('SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran')->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian IN ('Menunggu', 'Diproses')")->fetchColumn(),
];

render_header('Dashboard Admin');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard Admin</h1>
        <p class="text-muted mb-0 small">Selamat datang kembali! Berikut ringkasan operasional dan pendapatan laundry.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-soft badge-soft-secondary px-3 py-2">
            <i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?>
        </span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon indigo">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Semua Transaksi</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= number_format($statistics['transaksi']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Pelanggan</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= number_format($statistics['pelanggan']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Pendapatan</div>
                    <div class="h4 mb-0 fw-bold text-heading"><?= e(rupiah($statistics['pendapatan'])) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon amber">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Cucian Berjalan</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= number_format($statistics['proses']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-tags"></i>
                    </div>
                    <h2 class="h6 fw-bold mb-0">Kelola Layanan Laundry</h2>
                </div>
                <p class="text-muted small mb-4">Tambahkan tarif paket baru, sesuaikan harga per kilogram, atau ubah durasi pengerjaan cucian.</p>
                <a class="btn btn-primary" href="<?= e(app_url('admin/layanan.php')) ?>">
                    Buka Kelola Layanan <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="icon-box bg-info-subtle text-info">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <h2 class="h6 fw-bold mb-0">Laporan Keuangan & Transaksi</h2>
                </div>
                <p class="text-muted small mb-4">Lihat rekap transaksi harian, mingguan, atau bulanan serta cek total pembayaran yang telah diterima.</p>
                <a class="btn btn-outline-primary" href="<?= e(app_url('admin/laporan.php')) ?>">
                    Lihat Laporan Transaksi <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
