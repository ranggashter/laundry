<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');
$pdo = getPDO();

// ID pelanggan berasal dari sesi login, jadi pelanggan hanya melihat pesanannya sendiri.
$query = $pdo->prepare(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            d.nama_layanan
     FROM transaksi t LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? ORDER BY t.dibuat_pada DESC LIMIT 20'
);
$query->execute([$_SESSION['user']['id']]);
$transactions = $query->fetchAll();

// Cari pesanan aktif yang belum diambil untuk disorot di tracker
$activeOrder = null;
foreach ($transactions as $t) {
    if ($t['status_cucian'] !== 'Diambil') {
        $activeOrder = $t;
        break;
    }
}

render_header('Dashboard Pelanggan');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm">
            <i class="bi bi-person-heart"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Halo, <?= e($_SESSION['user']['nama']) ?>!</h1>
            <p class="text-muted mb-0 small">Pantau status pencucian baju Anda secara langsung tanpa perlu repot datang ke outlet.</p>
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

<div class="row g-4 mb-4">
    <!-- QR Card for Customer -->
    <?php if (!empty($_SESSION['user']['kode_pelanggan'])): ?>
    <div class="col-lg-5">
        <div class="card qr-card h-100 shadow-sm">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-primary text-white"><i class="bi bi-qr-code me-1"></i> Kartu Digital</span>
                        <small class="text-muted">LaundryApp Member</small>
                    </div>
                    <h2 class="h6 fw-bold text-heading mb-1">Kode QR Pelanggan Anda</h2>
                    <p class="text-muted small mb-3">Tunjukkan kode ini kepada kasir saat menitipkan cucian untuk proses penerimaan cepat.</p>
                </div>

                <div class="p-3 bg-white rounded-3 border d-flex align-items-center justify-content-between shadow-xs">
                    <div>
                        <small class="text-muted d-block" style="font-size: 0.68rem; text-transform: uppercase;">Kode Identitas</small>
                        <span class="fw-bold font-monospace fs-5 text-primary" id="customerQrText"><?= e($_SESSION['user']['kode_pelanggan']) ?></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-copy-target="#customerQrText" title="Salin Kode">
                        <i class="bi bi-clipboard"></i> Salin
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Laundry Progress Tracker (If any order is currently active) -->
    <div class="<?= !empty($_SESSION['user']['kode_pelanggan']) ? 'col-lg-7' : 'col-12' ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Status Pengerjaan Terkini</h2>
                        <small class="text-muted"><?= $activeOrder ? 'Pesanan #' . e($activeOrder['kode_nota']) : 'Tidak ada cucian yang sedang diproses' ?></small>
                    </div>
                </div>
                <?php if ($activeOrder): ?>
                    <span class="badge-soft <?= e(status_badge_class($activeOrder['status_cucian'])) ?>">
                        <i class="bi <?= e(status_icon($activeOrder['status_cucian'])) ?>"></i>
                        <?= e($activeOrder['status_cucian']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body p-4 d-flex flex-column justify-content-center">
                <?php if ($activeOrder): ?>
                    <?php 
                        $statusMap = ['Menunggu' => 1, 'Diproses' => 2, 'Selesai' => 3, 'Diambil' => 4];
                        $currStep = $statusMap[$activeOrder['status_cucian']] ?? 1;
                    ?>
                    <div class="laundry-stepper mb-3 px-2">
                        <div class="stepper-step <?= $currStep > 1 ? 'completed' : ($currStep === 1 ? 'current' : '') ?>">
                            <div class="stepper-bubble">
                                <i class="bi <?= $currStep > 1 ? 'bi-check-lg' : 'bi-hourglass-split' ?>"></i>
                            </div>
                            <span class="stepper-label">Menunggu</span>
                        </div>
                        <div class="stepper-step <?= $currStep > 2 ? 'completed' : ($currStep === 2 ? 'current' : '') ?>">
                            <div class="stepper-bubble">
                                <i class="bi <?= $currStep > 2 ? 'bi-check-lg' : 'bi-arrow-repeat' ?>"></i>
                            </div>
                            <span class="stepper-label">Diproses</span>
                        </div>
                        <div class="stepper-step <?= $currStep > 3 ? 'completed' : ($currStep === 3 ? 'current' : '') ?>">
                            <div class="stepper-bubble">
                                <i class="bi <?= $currStep > 3 ? 'bi-check-lg' : 'bi-sparkles' ?>"></i>
                            </div>
                            <span class="stepper-label">Selesai</span>
                        </div>
                        <div class="stepper-step <?= $currStep >= 4 ? 'completed' : '' ?>">
                            <div class="stepper-bubble">
                                <i class="bi bi-bag-check"></i>
                            </div>
                            <span class="stepper-label">Diambil</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top text-muted small">
                        <span>Paket: <strong><?= e($activeOrder['nama_layanan'] ?? 'Reguler') ?></strong> (<?= e($activeOrder['berat']) ?> kg)</span>
                        <a href="<?= e(app_url('pelanggan/nota.php?id=' . $activeOrder['id'])) ?>" class="btn btn-sm btn-outline-primary py-1">
                            <i class="bi bi-receipt"></i> Buka Nota
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Semua Cucian Anda Sudah Diambil</h6>
                        <p class="text-muted small mb-0">Bawa cucian Anda ke outlet laundry untuk menitipkan pesanan berikutnya.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="card shadow-sm">
    <div class="card-header-clean flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary">
                <i class="bi bi-basket"></i>
            </div>
            <div>
                <h2 class="h6 fw-bold mb-0">Daftar Pesanan Cucian Anda</h2>
                <small class="text-muted">Status diperbarui otomatis secara berkala</small>
            </div>
        </div>
        <div style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari nota / paket..." data-table-search="#customerRecentTable">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="customerRecentTable">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Tanggal Masuk</th>
                    <th>Layanan</th>
                    <th>Berat</th>
                    <th>Biaya</th>
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
                            <i class="bi bi-receipt"></i> Nota
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
