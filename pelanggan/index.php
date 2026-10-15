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

$activeCountQuery = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = ? AND status_cucian <> 'Diambil'");
$activeCountQuery->execute([$_SESSION['user']['id']]);
$activeOrderCount = (int) $activeCountQuery->fetchColumn();
$activePageSize = 2;
$activePageCount = max(1, (int) ceil($activeOrderCount / $activePageSize));
$activePage = filter_var($_GET['active_page'] ?? 1, FILTER_VALIDATE_INT);
$activePage = max(1, min($activePageCount, $activePage === false ? 1 : $activePage));
$activeOffset = ($activePage - 1) * $activePageSize;

$activeOrdersQuery = $pdo->prepare(
    "SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.status_cucian, d.nama_layanan
     FROM transaksi t
     LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? AND t.status_cucian <> 'Diambil'
     ORDER BY t.dibuat_pada DESC
     LIMIT $activePageSize OFFSET $activeOffset"
);
$activeOrdersQuery->execute([$_SESSION['user']['id']]);
$activeOrders = $activeOrdersQuery->fetchAll();

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
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.location.reload()" title="Perbarui status pesanan">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
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
            <div class="card-body p-4 d-flex flex-column justify-content-between gap-4">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-primary text-white"><i class="bi bi-qr-code me-1"></i> Kartu Digital</span>
                        <small class="text-muted">LaundryApp Member</small>
                    </div>
                    <h2 class="h6 fw-bold text-heading mb-1">Kode QR Pelanggan Anda</h2>
                    <p class="text-muted small mb-3">Tunjukkan kode ini kepada kasir saat menitipkan cucian untuk proses penerimaan cepat.</p>
                </div>

                <div class="customer-qr-panel d-flex flex-column flex-sm-row align-items-center gap-3 p-4 bg-white rounded-3 border shadow-xs">
                    <div class="p-2 border rounded-3 bg-white flex-shrink-0">
                        <div id="customerQrCode" data-qr-code="<?= e($_SESSION['user']['kode_pelanggan']) ?>" role="img" aria-label="QR pelanggan"></div>
                    </div>
                    <div class="text-center text-sm-start flex-grow-1">
                        <small class="text-muted d-block" style="font-size: 0.68rem; text-transform: uppercase;">Kode Identitas</small>
                        <span class="fw-bold font-monospace text-primary" id="customerQrText"><?= e($_SESSION['user']['kode_pelanggan']) ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary d-block mt-2 mx-auto mx-sm-0" data-copy-target="#customerQrText" title="Salin Kode">
                            <i class="bi bi-clipboard"></i> Salin Kode
                        </button>
                        <button type="button" class="btn btn-sm btn-primary d-block mt-2 mx-auto mx-sm-0" data-download-qr="#customerQrCode" data-qr-filename="qr-pelanggan-<?= e($_SESSION['user']['kode_pelanggan']) ?>">
                            <i class="bi bi-download"></i> Unduh QR
                        </button>
                    </div>
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
                        <small class="text-muted"><?= $activeOrderCount ? $activeOrderCount . ' transaksi belum diambil' : 'Tidak ada cucian yang sedang diproses' ?></small>
                    </div>
                </div>
            </div>

            <div class="card-body p-3">
                <?php if ($activeOrders): ?>
                    <?php foreach ($activeOrders as $index => $activeOrder): ?>
                        <?php
                            $statusMap = ['Menunggu' => 1, 'Diproses' => 2, 'Selesai' => 3, 'Diambil' => 4];
                            $currStep = $statusMap[$activeOrder['status_cucian']] ?? 1;
                        ?>
                        <div class="active-order-item <?= $index > 0 ? 'border-top pt-3 mt-3' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <span class="font-monospace fw-bold text-primary"><?= e($activeOrder['kode_nota']) ?></span>
                                    <div class="small text-muted">
                                        <?= e($activeOrder['nama_layanan'] ?? 'Reguler') ?> · <?= e($activeOrder['berat']) ?> kg
                                    </div>
                                </div>
                                <span class="badge-soft <?= e(status_badge_class($activeOrder['status_cucian'])) ?>">
                                    <i class="bi <?= e(status_icon($activeOrder['status_cucian'])) ?>"></i>
                                    <?= e($activeOrder['status_cucian']) ?>
                                </span>
                            </div>
                            <div class="laundry-stepper mb-2 px-2">
                                <div class="stepper-step <?= $currStep > 1 ? 'completed' : ($currStep === 1 ? 'current' : '') ?>">
                                    <div class="stepper-bubble"><i class="bi <?= $currStep > 1 ? 'bi-check-lg' : 'bi-hourglass-split' ?>"></i></div>
                                    <span class="stepper-label">Menunggu</span>
                                </div>
                                <div class="stepper-step <?= $currStep > 2 ? 'completed' : ($currStep === 2 ? 'current' : '') ?>">
                                    <div class="stepper-bubble"><i class="bi <?= $currStep > 2 ? 'bi-check-lg' : 'bi-arrow-repeat' ?>"></i></div>
                                    <span class="stepper-label">Diproses</span>
                                </div>
                                <div class="stepper-step <?= $currStep > 3 ? 'completed' : ($currStep === 3 ? 'current' : '') ?>">
                                    <div class="stepper-bubble"><i class="bi <?= $currStep > 3 ? 'bi-check-lg' : 'bi-sparkles' ?>"></i></div>
                                    <span class="stepper-label">Selesai</span>
                                </div>
                                <div class="stepper-step <?= $currStep >= 4 ? 'completed' : '' ?>">
                                    <div class="stepper-bubble"><i class="bi bi-bag-check"></i></div>
                                    <span class="stepper-label">Diambil</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <a href="<?= e(app_url('pelanggan/nota.php?id=' . $activeOrder['id'])) ?>" class="btn btn-sm btn-outline-primary py-1">
                                    <i class="bi bi-receipt"></i> Buka Nota
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($activePageCount > 1): ?>
                        <nav class="d-flex justify-content-between align-items-center border-top mt-3 pt-3" aria-label="Halaman status pengerjaan">
                            <?php if ($activePage > 1): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('pelanggan/index.php?active_page=' . ($activePage - 1))) ?>">
                                    <i class="bi bi-chevron-left"></i> Sebelumnya
                                </a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <span class="small text-muted">Halaman <?= $activePage ?> dari <?= $activePageCount ?></span>
                            <?php if ($activePage < $activePageCount): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('pelanggan/index.php?active_page=' . ($activePage + 1))) ?>">
                                    Berikutnya <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
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
        <span class="text-muted small">Status otomatis diperbarui</span>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php if ($transactions): ?>
<script>
    // Muat ulang halaman setelah 15 detik agar status terbaru terlihat.
    setTimeout(function () {
        window.location.reload();
    }, 15000);
</script>
<?php endif; ?>
<?php render_footer(); ?>
