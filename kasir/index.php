<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
// Hitung cucian di setiap tahap dan ambil beberapa pesanan terbaru.
$summary = [
    'baru' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Menunggu'")->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Diproses'")->fetchColumn(),
    'selesai' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Selesai'")->fetchColumn(),
];
$recent = $pdo->query(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.total, t.status_cucian, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id ORDER BY t.id DESC LIMIT 8'
)->fetchAll();
render_header('Dashboard Kasir');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard Kasir</h1>
        <p class="text-muted mb-0 small">Pantau status antrean cucian dan transaksi masuk secara real-time.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(app_url('kasir/buat_pesanan.php')) ?>">
        <i class="bi bi-plus-circle-fill"></i> Buat Pesanan Baru
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon amber">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Cucian Menunggu</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= $summary['baru'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon sky">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Sedang Diproses</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= $summary['proses'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="stat-icon emerald">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Selesai Dicuci</div>
                    <div class="h2 mb-0 fw-bold text-heading"><?= $summary['selesai'] ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <h2 class="h6 fw-bold mb-0">Transaksi Terbaru</h2>
        </div>
        <span class="text-muted small">8 transaksi terakhir</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Pelanggan</th>
                    <th>Waktu Masuk</th>
                    <th>Total Biaya</th>
                    <th>Status Cucian</th>
                    <th class="text-end">Aksi</th>
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
                    </td>
                    <td>
                        <div class="text-heading"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted small"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="fw-bold text-heading"><?= e(rupiah($item['total'])) ?></span>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($item['status_cucian'])) ?>">
                            <?= e($item['status_cucian']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('kasir/pembayaran.php?id=' . $item['id'])) ?>">
                            <i class="bi bi-credit-card"></i> Buka Nota
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr>
                    <td colspan="6">
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
