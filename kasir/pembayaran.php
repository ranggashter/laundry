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
    $amount = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_FLOAT);
    if (!$transactionId || $amount === false || $amount <= 0) {
        $error = 'Pilih transaksi dan masukkan jumlah pembayaran yang valid.';
    } else {
        // Proses catatan pembayaran dan total dibayar sebagai satu kesatuan.
        $pdo->beginTransaction();
        try {
            // Kunci baris transaksi agar dua pembayaran bersamaan tidak melampaui sisa tagihan.
            $lock = $pdo->prepare('SELECT total, dibayar FROM transaksi WHERE id = ? FOR UPDATE');
            $lock->execute([$transactionId]);
            $transaction = $lock->fetch();
            if (!$transaction) {
                $error = 'Transaksi tidak ditemukan.';
            } else {
                $remaining = (float) $transaction['total'] - (float) $transaction['dibayar'];
                if ($amount > $remaining) {
                    $error = 'Jumlah pembayaran melebihi sisa tagihan ' . rupiah($remaining) . '.';
                } else {
                    $kind = $amount >= $remaining ? 'Pelunasan' : 'DP';
                    $payment = $pdo->prepare('INSERT INTO pembayaran (transaksi_id, kasir_id, jumlah, jenis) VALUES (?, ?, ?, ?)');
                    $payment->execute([$transactionId, $_SESSION['user']['id'], $amount, $kind]);
                    $paid = (float) $transaction['dibayar'] + $amount;
                    $newStatus = $paid >= (float) $transaction['total'] ? 'Lunas' : 'Belum Lunas';
                    $update = $pdo->prepare('UPDATE transaksi SET dibayar = ?, status_pembayaran = ? WHERE id = ?');
                    $update->execute([$paid, $newStatus, $transactionId]);
                    $pdo->commit();
                    redirect('kasir/pembayaran.php?id=' . $transactionId);
                }
            }
            $pdo->rollBack();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}

$pending = $pdo->query(
    "SELECT t.id, t.kode_nota, t.total, t.dibayar, u.nama AS nama_pelanggan
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
        $history = $pdo->prepare('SELECT jumlah, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id DESC');
        $history->execute([$transactionId]);
        $payments = $history->fetchAll();
    }
}
render_header('Pembayaran');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon emerald shadow-sm">
            <i class="bi bi-credit-card-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Catat Pembayaran</h1>
            <p class="text-muted mb-0 small">Terima uang muka (DP) atau pelunasan tagihan cucian pelanggan.</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-top" style="top: 85px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Pilih Nota Tagihan</h2>
                        <small class="text-muted">Cari nota yang belum lunas</small>
                    </div>
                </div>

                <form method="get">
                    <div class="mb-3">
                        <label class="form-label" for="pilih-id">
                            <i class="bi bi-search text-muted"></i> Daftar Nota Belum Lunas
                        </label>
                        <select class="form-select" id="pilih-id" name="id" required>
                            <option value="">-- Pilih Transaksi --</option>
                            <?php foreach ($pending as $item): ?>
                                <option value="<?= e($item['id']) ?>" <?= (int) $transactionId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['kode_nota']) ?> — <?= e($item['nama_pelanggan']) ?> (Sisa <?= e(rupiah($item['total'] - $item['dibayar'])) ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current && $current['status_pembayaran'] === 'Lunas'): ?>
                                <option value="<?= e($current['id']) ?>" selected><?= e($current['kode_nota']) ?> — Sudah Lunas</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button class="btn btn-outline-primary w-100" type="submit">
                        <i class="bi bi-folder2-open"></i> Buka Rincian Nota
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($current): ?>
            <?php $remaining = max(0, (float) $current['total'] - (float) $current['dibayar']); ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
                        <div>
                            <span class="badge-soft badge-soft-primary mb-1">
                                <i class="bi bi-hash"></i> <?= e($current['kode_nota']) ?>
                            </span>
                            <h2 class="h5 fw-bold mb-0 text-heading">Pelanggan: <?= e($current['nama_pelanggan']) ?></h2>
                            <small class="text-muted">Waktu masuk: <?= date('d M Y, H:i', strtotime($current['dibuat_pada'])) ?> WIB</small>
                        </div>
                        <div>
                            <?php if ($remaining <= 0): ?>
                                <span class="badge-soft badge-soft-success fs-6"><i class="bi bi-check-circle-fill"></i> Lunas</span>
                            <?php else: ?>
                                <span class="badge-soft badge-soft-danger fs-6"><i class="bi bi-hourglass-split"></i> Belum Lunas</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4 text-center">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Total Biaya</small>
                                <strong class="fs-6 text-heading"><?= e(rupiah($current['total'])) ?></strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Telah Dibayar</small>
                                <strong class="fs-6 text-success"><?= e(rupiah($current['dibayar'])) ?></strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block">Sisa Tagihan</small>
                                <strong class="fs-6 text-danger"><?= e(rupiah($remaining)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if ($remaining > 0): ?>
                        <div class="card border-0 bg-primary-subtle p-3 mb-4">
                            <h3 class="h6 fw-bold text-primary mb-2">Formulir Pembayaran</h3>
                            <form method="post" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="transaksi_id" value="<?= e($current['id']) ?>">
                                <div class="col-sm-8">
                                    <label class="form-label text-heading" for="jumlah">Jumlah Bayar</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input class="form-control" type="number" id="jumlah" name="jumlah" min="1" max="<?= e($remaining) ?>" step="any" placeholder="Ketik jumlah uang" value="<?= e($remaining) ?>" required>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <button class="btn btn-primary w-100" type="submit">
                                        <i class="bi bi-check2"></i> Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-4">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <div>Seluruh tagihan cucian pada nota ini telah lunas dibayar.</div>
                        </div>
                    <?php endif; ?>

                    <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-muted"></i> Riwayat Pembayaran Masuk
                    </h3>
                    <?php if (!$payments): ?>
                        <p class="text-muted small mb-0">Belum ada riwayat pembayaran yang tercatat.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded-3">
                            <?php foreach ($payments as $payment): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon emerald" style="width: 32px; height: 32px; font-size: 0.9rem; border-radius: 8px;">
                                            <i class="bi bi-cash"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-heading d-block"><?= e($payment['jenis']) ?></span>
                                            <small class="text-muted"><?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?> WIB</small>
                                        </div>
                                    </div>
                                    <strong class="text-success fs-6"><?= e(rupiah($payment['jumlah'])) ?></strong>
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
                        <p class="text-muted small mb-0">Pilih nota di panel sebelah kiri untuk melihat rincian tagihan dan mencatat pembayaran.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
