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
render_header('Riwayat Transaksi');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm">
            <i class="bi bi-clock-history"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Riwayat Transaksi Laundry</h1>
            <p class="text-muted mb-0 small">Daftar seluruh transaksi pencucian baju yang pernah Anda lakukan.</p>
        </div>
    </div>
    <span class="badge-soft badge-soft-primary px-3 py-2">
        <i class="bi bi-receipt me-1"></i> Total <?= count($transactions) ?> Transaksi
    </span>
</div>

<div class="card shadow-sm">
    <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-text text-primary"></i>
            <h2 class="h6 fw-bold mb-0">Daftar Nota Cucian Anda</h2>
        </div>
        <span class="text-muted small">Diurutkan dari transaksi terbaru</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Tanggal</th>
                    <th>Paket Layanan</th>
                    <th>Berat</th>
                    <th>Total</th>
                    <th>Terbayar</th>
                    <th>Status Cucian</th>
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
                        <div class="text-heading"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted small"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
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
                            <?= e($item['status_cucian']) ?>
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
                    <td colspan="8">
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
