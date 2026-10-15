<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$trackingInput = $_GET['kode'] ?? '';
$trackingCode = is_string($trackingInput) ? strtoupper(trim($trackingInput)) : '';
$trackedCustomer = null;
$trackedTransactions = [];
$trackingError = '';

if ($trackingCode !== '') {
    if (str_starts_with($trackingCode, 'NOTA:')) {
        $noteCode = substr($trackingCode, 5);
        if (strlen($noteCode) > 40 || !preg_match('/^[A-Z0-9_-]+$/', $noteCode)) {
            $trackingError = 'Format kode nota tidak valid.';
        } else {
            $noteQuery = $pdo->prepare('SELECT id FROM transaksi WHERE kode_nota = ? LIMIT 1');
            $noteQuery->execute([$noteCode]);
            $transactionId = $noteQuery->fetchColumn();
            if ($transactionId) {
                redirect('kasir/nota.php?id=' . (int) $transactionId);
            }
            $trackingError = 'Transaksi dengan kode nota tersebut tidak ditemukan.';
        }
    } elseif (strlen($trackingCode) > 40 || !preg_match('/^[A-Z0-9_-]+$/', $trackingCode)) {
        $trackingError = 'Format kode pelanggan tidak valid.';
    } else {
        $customerQuery = $pdo->prepare("SELECT id, nama, kode_pelanggan FROM users WHERE role = 'pelanggan' AND kode_pelanggan = ? LIMIT 1");
        $customerQuery->execute([$trackingCode]);
        $trackedCustomer = $customerQuery->fetch() ?: null;

        if ($trackedCustomer) {
            $transactionQuery = $pdo->prepare(
                'SELECT id, kode_nota, dibuat_pada, berat, total, dibayar, status_cucian, status_pembayaran
                 FROM transaksi WHERE pelanggan_id = ? ORDER BY dibuat_pada DESC LIMIT 50'
            );
            $transactionQuery->execute([$trackedCustomer['id']]);
            $trackedTransactions = $transactionQuery->fetchAll();
        } else {
            $trackingError = 'Kode tidak ditemukan. Pastikan QR berasal dari akun pelanggan aktif.';
        }
    }
}

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
        <button class="btn btn-outline-secondary" type="button" onclick="window.location.reload()" title="Perbarui data dashboard">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
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

<!-- Customer QR Transaction Tracker -->
<section class="card shadow-sm mb-4" aria-labelledby="tracking-title">
    <div class="card-header-clean">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-qr-code-scan"></i></div>
            <div>
                <h2 class="h6 fw-bold mb-0" id="tracking-title">Lacak Transaksi Pelanggan</h2>
                <small class="text-muted">Scan QR pelanggan atau masukkan kode identitasnya</small>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <form class="row g-2 align-items-start" id="tracking-form" method="get" action="<?= e(app_url('kasir/index.php')) ?>">
            <div class="col-12 col-md">
                <label class="visually-hidden" for="tracking-code">Kode QR pelanggan atau nota</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input class="form-control" id="tracking-code" name="kode" value="<?= e($trackingCode) ?>" placeholder="Kode pelanggan atau NOTA:TRX-..." maxlength="64" autocomplete="off">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Lacak</button>
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-outline-primary" type="button" id="scan-camera-tracking">
                    <i class="bi bi-camera-video"></i> <span data-scan-label>Scan Kamera</span>
                </button>
            </div>
            <div class="col-12">
                <div id="qr-reader-tracking" class="mb-2" hidden></div>
                <div class="small text-muted" id="tracking-scan-message">Scan QR pelanggan untuk melihat riwayat atau QR nota untuk membuka transaksinya.</div>
            </div>
        </form>

        <?php if ($trackingCode !== ''): ?>
        <div class="border-top mt-4 pt-4">
            <?php if ($trackingError): ?>
                <div class="alert alert-warning mb-0" role="status"><i class="bi bi-exclamation-circle me-2"></i><?= e($trackingError) ?></div>
            <?php else: ?>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1"><?= e($trackedCustomer['nama']) ?></h3>
                        <span class="font-monospace small text-muted"><?= e($trackedCustomer['kode_pelanggan']) ?></span>
                    </div>
                    <span class="badge-soft badge-soft-primary"><?= count($trackedTransactions) ?> transaksi ditemukan</span>
                </div>
                <?php if ($trackedTransactions): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Kode Nota</th><th>Tanggal</th><th>Berat</th><th>Total</th><th>Cucian</th><th>Pembayaran</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trackedTransactions as $transaction): ?>
                            <tr>
                                <td class="font-monospace fw-semibold text-primary"><?= e($transaction['kode_nota']) ?></td>
                                <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?></td>
                                <td><?= e($transaction['berat']) ?> kg</td>
                                <td class="text-nowrap"><?= e(rupiah($transaction['total'])) ?></td>
                                <td><span class="badge-soft <?= e(status_badge_class($transaction['status_cucian'])) ?>"><?= e($transaction['status_cucian']) ?></span></td>
                                <td><span class="badge-soft <?= e(status_badge_class($transaction['status_pembayaran'])) ?>"><?= e($transaction['status_pembayaran']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('kasir/nota.php?id=' . $transaction['id'])) ?>" title="Lihat nota"><i class="bi bi-receipt"></i></a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= e(app_url('kasir/pembayaran.php?id=' . $transaction['id'])) ?>" title="Catat pembayaran"><i class="bi bi-cash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="text-muted small">Pelanggan ini belum memiliki transaksi.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

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
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<?php render_footer(); ?>
