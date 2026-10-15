<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$error = '';
$transactionId = filter_var($_GET['id'] ?? $_POST['transaksi_id'] ?? null, FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek input pembayaran sebelum mengubah tagihan.
    verify_csrf();
    $tendered = filter_var($_POST['uang_diterima'] ?? $_POST['jumlah'] ?? null, FILTER_VALIDATE_FLOAT);
    if (!$transactionId || $tendered === false || $tendered <= 0) {
        $error = 'Pilih transaksi dan masukkan jumlah pembayaran yang valid (lebih dari 0).';
    } else {
        // Proses catatan pembayaran dan total dibayar sebagai satu kesatuan.
        $pdo->beginTransaction();
        try {
            // Kunci baris transaksi agar dua pembayaran bersamaan tidak melampaui sisa tagihan.
            $lock = $pdo->prepare('SELECT id, kode_nota, total, dibayar, status_pembayaran FROM transaksi WHERE id = ? FOR UPDATE');
            $lock->execute([$transactionId]);
            $transaction = $lock->fetch();
            if (!$transaction) {
                $error = 'Transaksi tidak ditemukan.';
                $pdo->rollBack();
            } else {
                $remaining = max(0, (float) $transaction['total'] - (float) $transaction['dibayar']);
                if ($remaining <= 0) {
                    $error = 'Transaksi ini sudah lunas.';
                    $pdo->rollBack();
                } else {
                    if ($tendered >= $remaining) {
                        // Uang pas atau uang lebih: tagihan lunas, sisanya dihitung sebagai kembalian
                        $paymentAmount = $remaining;
                        $change = $tendered - $remaining;
                        $kind = 'Pelunasan';
                        $newStatus = 'Lunas';
                        $newPaid = (float) $transaction['total'];
                    } else {
                        // Uang kurang: dicatat sebagai DP, status tetap Belum Lunas
                        $paymentAmount = $tendered;
                        $change = 0.0;
                        $kind = 'DP';
                        $newStatus = 'Belum Lunas';
                        $newPaid = (float) $transaction['dibayar'] + $paymentAmount;
                    }

                    $payment = $pdo->prepare(
                        'INSERT INTO pembayaran (transaksi_id, kasir_id, jumlah, uang_diterima, kembalian, jenis) 
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $payment->execute([$transactionId, $_SESSION['user']['id'], $paymentAmount, $tendered, $change, $kind]);
                    $paymentId = (int) $pdo->lastInsertId();

                    $update = $pdo->prepare('UPDATE transaksi SET dibayar = ?, status_pembayaran = ? WHERE id = ?');
                    $update->execute([$newPaid, $newStatus, $transactionId]);

                    $pdo->commit();

                    // Simpan data pembayaran terakhir untuk notifikasi dan cetak nota
                    $_SESSION['last_payment'] = [
                        'payment_id' => $paymentId,
                        'transaction_id' => $transactionId,
                        'kode_nota' => $transaction['kode_nota'],
                        'tendered' => $tendered,
                        'amount' => $paymentAmount,
                        'change' => $change,
                        'kind' => $kind,
                        'status' => $newStatus,
                    ];

                    // Langsung arahkan ke halaman nota untuk diberikan dan dicetak
                    redirect('kasir/nota.php?id=' . $transactionId . '&new_payment=1');
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}

$pending = $pdo->query(
    "SELECT t.id, t.kode_nota, t.total, t.dibayar, t.dibuat_pada, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id
     WHERE t.status_pembayaran = 'Belum Lunas' ORDER BY t.dibuat_pada DESC"
)->fetchAll();

// Jika ada ID di URL, tampilkan detail nota dan riwayat pembayarannya.
$current = null;
$payments = [];
if ($transactionId) {
    $find = $pdo->prepare('SELECT t.*, u.nama AS nama_pelanggan FROM transaksi t JOIN users u ON u.id = t.pelanggan_id WHERE t.id = ?');
    $find->execute([$transactionId]);
    $current = $find->fetch() ?: null;
    if ($current) {
        $history = $pdo->prepare('SELECT id, jumlah, uang_diterima, kembalian, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id DESC');
        $history->execute([$transactionId]);
        $payments = $history->fetchAll();
    }
}

render_header('Catat Pembayaran');
?>
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon emerald shadow-sm">
            <i class="bi bi-credit-card-2-front-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Catat Pembayaran Laundry</h1>
            <p class="text-muted mb-0 small">Terima uang muka (DP) atau pelunasan tagihan cucian pelanggan dengan kalkulasi kembalian otomatis.</p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-soft badge-soft-danger px-3 py-2">
            <i class="bi bi-exclamation-circle me-1"></i> <?= count($pending) ?> Nota Belum Lunas
        </span>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Unpaid Orders Selector -->
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Pilih Nota Tagihan</h2>
                        <small class="text-muted">Cari nota yang perlu dibayar</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <form method="get">
                    <div class="mb-3">
                        <label class="form-label" for="pilih-id">
                            <i class="bi bi-search text-muted"></i> Dropdown Pilih Nota
                        </label>
                        <select class="form-select" id="pilih-id" name="id" onchange="this.form.submit()" required>
                            <option value="">-- Pilih Transaksi --</option>
                            <?php foreach ($pending as $item): ?>
                                <option value="<?= e($item['id']) ?>" <?= (int) $transactionId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['kode_nota']) ?> — <?= e($item['nama_pelanggan']) ?> (Sisa <?= e(rupiah($item['total'] - $item['dibayar'])) ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current && $current['status_pembayaran'] === 'Lunas'): ?>
                                <option value="<?= e($current['id']) ?>" selected><?= e($current['kode_nota']) ?> — <?= e($current['nama_pelanggan']) ?> (Lunas)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>

                <div class="sidebar-heading px-0 mt-3 mb-2">Daftar Tagihan Belum Lunas:</div>
                <div class="list-group list-group-flush border rounded-3 overflow-auto" style="max-height: 380px;">
                    <?php foreach ($pending as $item): ?>
                        <?php $sisa = (float) $item['total'] - (float) $item['dibayar']; ?>
                        <a href="<?= e(app_url('kasir/pembayaran.php?id=' . $item['id'])) ?>" class="list-group-item list-group-item-action p-3 <?= (int) $transactionId === (int) $item['id'] ? 'bg-primary-subtle' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="font-monospace fw-bold text-primary small"><?= e($item['kode_nota']) ?></span>
                                <span class="badge-soft badge-soft-danger" style="font-size: 0.7rem;">Sisa <?= e(rupiah($sisa)) ?></span>
                            </div>
                            <div class="fw-semibold text-heading small"><?= e($item['nama_pelanggan']) ?></div>
                            <small class="text-muted" style="font-size: 0.72rem;"><?= date('d/m/Y H:i', strtotime($item['dibuat_pada'])) ?> WIB</small>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$pending): ?>
                        <div class="p-4 text-center text-muted small">
                            <i class="bi bi-check2-all text-success fs-3 d-block mb-1"></i>
                            Semua tagihan cucian telah lunas!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Payment Details & Form -->
    <div class="col-lg-7">
        <?php if ($current): ?>
            <?php 
                $total = (float) $current['total'];
                $dibayar = (float) $current['dibayar'];
                $remaining = max(0, $total - $dibayar);
                $percent = $total > 0 ? min(100, round(($dibayar / $total) * 100)) : 100;
            ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4 pb-3 border-bottom">
                        <div>
                            <span class="badge-soft badge-soft-primary font-monospace fs-6 mb-2">
                                <i class="bi bi-hash"></i> <?= e($current['kode_nota']) ?>
                            </span>
                            <h2 class="h5 fw-bold mb-1 text-heading">Pelanggan: <?= e($current['nama_pelanggan']) ?></h2>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i> Dibuat: <?= date('d M Y, H:i', strtotime($current['dibuat_pada'])) ?> WIB
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?= e(app_url('kasir/nota.php?id=' . $current['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Cetak nota transaksi ini">
                                <i class="bi bi-printer-fill"></i> Cetak Nota
                            </a>
                            <?php if ($remaining <= 0): ?>
                                <span class="badge-soft badge-soft-success fs-6 px-3 py-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> Lunas
                                </span>
                            <?php else: ?>
                                <span class="badge-soft badge-soft-danger fs-6 px-3 py-2">
                                    <i class="bi bi-hourglass-split me-1"></i> Belum Lunas
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Financial Summary Tiles -->
                    <div class="row g-3 mb-4 text-center">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Total Tagihan</small>
                                <strong class="fs-6 text-heading"><?= e(rupiah($total)) ?></strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Telah Dibayar</small>
                                <strong class="fs-6 text-success"><?= e(rupiah($dibayar)) ?></strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Sisa Tagihan</small>
                                <strong class="fs-6 <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= e(rupiah($remaining)) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Progress Bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                            <span>Status Pelunasan</span>
                            <span class="fw-bold"><?= $percent ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar <?= $percent >= 100 ? 'bg-success' : 'bg-primary' ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Payment Input Form -->
                    <?php if ($remaining > 0): ?>
                        <div class="card border-0 bg-primary-subtle p-4 mb-4" style="border-radius: var(--radius-lg);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-wallet2 text-primary fs-5"></i>
                                <h3 class="h6 fw-bold text-primary mb-0">Formulir Pembayaran</h3>
                            </div>

                            <form method="post" id="form-pembayaran">
                                <?= csrf_field() ?>
                                <input type="hidden" name="transaksi_id" value="<?= e($current['id']) ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label text-heading fw-bold" for="uang_diterima">
                                        <i class="bi bi-cash-stack text-success"></i> Uang Tunai Diterima (Bisa Lebih / Kurang)
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text fw-bold">Rp</span>
                                        <input class="form-control fw-bold fs-5" type="number" id="uang_diterima" name="uang_diterima" min="1" step="any" placeholder="Ketik jumlah uang diserahkan..." value="<?= e($remaining) ?>" data-remaining="<?= e($remaining) ?>" required autofocus>
                                    </div>
                                    </div>
                                    <!-- Quick Pay Presets -->
                                    <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                        <span class="text-muted small">Pilihan cepat:</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-quick-pay btn-quick-cash py-1" data-amount="<?= e($remaining) ?>" data-val="<?= e($remaining) ?>">
                                            <i class="bi bi-check2"></i> Bayar Lunas Pas (<?= e(rupiah($remaining)) ?>)
                                        </button>
                                        <?php if ($remaining > 10000): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-pay btn-quick-cash py-1" data-amount="<?= e(round($remaining / 2)) ?>" data-val="<?= e(round($remaining / 2)) ?>">
                                                DP 50% (<?= e(rupiah(round($remaining / 2))) ?>)
                                            </button>
                                        <?php endif; ?>
                                        <?php foreach ([10000, 20000, 50000, 100000] as $nominal): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-cash py-1" data-val="<?= $nominal ?>">
                                                <?= e(rupiah($nominal)) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Kotak Kalkulasi Real-Time (Kembalian / DP) -->
                                <div class="card border p-3 mb-3 bg-white shadow-xs" id="calc-preview-box">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small">Status Pembayaran:</span>
                                        <span id="calc-status-badge" class="badge-soft badge-soft-success">Lunas</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small">Dipotong ke Tagihan:</span>
                                        <strong id="calc-paid-amount" class="text-heading"><?= e(rupiah($remaining)) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="fw-bold text-heading" id="calc-result-label">Kembalian:</span>
                                        <strong id="calc-result-amount" class="fs-5 text-success">Rp 0</strong>
                                    </div>
                                    <div id="calc-note" class="small text-muted mt-1">Uang pas sesuai sisa tagihan.</div>
                                </div>

                                <button class="btn btn-primary w-100 py-2 fs-6 shadow-sm" type="submit">
                                    <i class="bi bi-printer-fill me-1"></i> Simpan Pembayaran & Cetak Nota
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center mb-4 p-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill fs-4"></i>
                                <div>Seluruh tagihan cucian pada nota ini telah <strong>lunas dibayar</strong>.</div>
                            </div>
                            <a href="<?= e(app_url('kasir/nota.php?id=' . $current['id'])) ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-printer-fill"></i> Cetak Ulang Nota
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Payment Logs Timeline -->
                    <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-muted"></i> Riwayat Pembayaran Masuk
                    </h3>
                    <?php if (!$payments): ?>
                        <div class="p-3 bg-light rounded-3 text-center text-muted small">
                            Belum ada riwayat pembayaran yang tercatat untuk nota ini.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded-3">
                            <?php foreach ($payments as $payment): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon <?= $payment['jenis'] === 'Pelunasan' ? 'emerald' : 'amber' ?>" style="width: 34px; height: 34px; font-size: 0.95rem; border-radius: 8px;">
                                            <i class="bi bi-cash"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-heading d-block">
                                                <?= e($payment['jenis']) ?>
                                                <?php if ((float) $payment['kembalian'] > 0): ?>
                                                    <span class="badge-soft badge-soft-primary ms-1" style="font-size: 0.7rem;">Kembalian <?= e(rupiah($payment['kembalian'])) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <small class="text-muted">
                                                <?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?> WIB
                                                <?php if ((float) $payment['uang_diterima'] > 0): ?>
                                                    · Uang diterima: <?= e(rupiah($payment['uang_diterima'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-success fs-6 d-block"><?= e(rupiah($payment['jumlah'])) ?></strong>
                                        <small class="text-muted" style="font-size: 0.75rem;">masuk tagihan</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-credit-card-2-front"></i>
                        </div>
                        <h6 class="fw-bold text-heading">Pilih Transaksi Terlebih Dahulu</h6>
                        <p class="text-muted small mb-0">Pilih nota di panel sebelah kiri untuk melihat rincian tagihan dan mencatat pembayaran pelanggan.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
