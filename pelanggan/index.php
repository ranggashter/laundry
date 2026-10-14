<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');
$pdo = getPDO();
// ID pelanggan berasal dari sesi login, jadi pelanggan hanya melihat pesanannya sendiri.
$query = $pdo->prepare(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.status_pembayaran, t.status_cucian,
            d.nama_layanan
     FROM transaksi t LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? ORDER BY t.dibuat_pada DESC LIMIT 20'
);
$query->execute([$_SESSION['user']['id']]);
$transactions = $query->fetchAll();
render_header('Dashboard Pelanggan');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm">
            <i class="bi bi-person-heart"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Halo, <?= e($_SESSION['user']['nama']) ?>!</h1>
            <p class="text-muted mb-0 small">Pantau status pencucian baju Anda secara langsung tanpa perlu repot datang.</p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-soft badge-soft-info px-3 py-2">
            <i class="bi bi-arrow-clockwise me-1"></i> Auto-refresh 15 detik
        </span>
        <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">
            <i class="bi bi-clock-history"></i> Semua Riwayat
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['user']['kode_pelanggan'])): ?>
    <div class="card shadow-sm border-0 bg-primary-subtle p-3 mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-qr-code fs-3 text-primary"></i>
                <div>
                    <span class="fw-bold text-heading d-block">Kode QR Pelanggan Anda</span>
                    <small class="text-muted">Tunjukkan kode ini ke kasir saat menitipkan cucian untuk proses cepat.</small>
                </div>
            </div>
            <span class="badge-soft badge-soft-primary fs-6 px-3 py-2 font-monospace">
                <?= e($_SESSION['user']['kode_pelanggan']) ?>
            </span>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-basket text-primary"></i>
            <h2 class="h6 fw-bold mb-0">Pesanan Cucian Terbaru</h2>
        </div>
        <span class="text-muted small">Status otomatis diperbarui</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Tanggal</th>
                    <th>Layanan</th>
                    <th>Berat</th>
                    <th>Status Cucian</th>
                    <th>Status Pembayaran</th>
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
                        <span class="badge-soft <?= e(status_badge_class($item['status_cucian'])) ?>">
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
                            <i class="bi bi-receipt"></i> Nota
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h6 class="fw-bold">Belum Ada Pesanan Cucian</h6>
                            <p class="text-muted small mb-0">Nota digital Anda akan otomatis muncul di sini setelah kasir mencatat cucian Anda.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($transactions): ?>
<script>
    // Muat ulang halaman setelah 15 detik agar status terbaru terlihat.
    setTimeout(function () {
        window.location.reload();
    }, 15000);
</script>
<?php endif; ?>
<?php render_footer(); ?>
