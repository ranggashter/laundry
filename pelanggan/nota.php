<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');
$pdo = getPDO();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

// Cocokkan ID nota dan ID pelanggan agar nota orang lain tidak bisa dibuka.
$query = $pdo->prepare(
    'SELECT t.*, u.nama AS nama_pelanggan, u.kode_pelanggan, d.nama_layanan, d.harga_per_kg
     FROM transaksi t
     JOIN users u ON u.id = t.pelanggan_id
     LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.id = ? AND t.pelanggan_id = ?'
);
$query->execute([$id, $_SESSION['user']['id']]);
$transaction = $query->fetch();
if (!$transaction) {
    http_response_code(404);
    render_header('Nota Tidak Ditemukan');
    echo '<div class="alert alert-warning">Nota tidak ditemukan atau bukan milik akun Anda.</div>';
    render_footer();
    exit;
}
$paymentQuery = $pdo->prepare('SELECT jumlah, uang_diterima, kembalian, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id');
$paymentQuery->execute([$id]);
$payments = $paymentQuery->fetchAll();
render_header('Nota Digital');
?>
<div class="receipt">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">
            <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
        </a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Cetak Nota
        </button>
    </div>
    <div class="card shadow-md border-0">
        <div class="card-body p-4 p-md-5">
            <div class="text-center border-bottom pb-4 mb-4">
                <div class="brand-badge mx-auto mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
                <h1 class="h4 fw-bold mb-1">Nota Transaksi Cucian</h1>
                <p class="text-muted small mb-2">Simpan nota digital ini sebagai bukti pengambilan cucian.</p>
                <div class="badge-soft badge-soft-primary px-3 py-2 font-monospace fs-6">
                    <?= e($transaction['kode_nota']) ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="text-muted small">Nama Pelanggan</div>
                    <div class="fw-bold text-heading fs-6"><?= e($transaction['nama_pelanggan']) ?></div>
                    <?php if (!empty($transaction['kode_pelanggan'])): ?>
                        <div class="small font-monospace text-muted"><?= e($transaction['kode_pelanggan']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <div class="text-muted small">Waktu Penerimaan</div>
                    <div class="fw-semibold text-heading"><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?> WIB</div>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Paket Layanan</th>
                            <th>Berat</th>
                            <th>Tarif/kg</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <span class="fw-semibold text-heading"><?= e($transaction['nama_layanan'] ?? '-') ?></span>
                            </td>
                            <td><?= e($transaction['berat']) ?> kg</td>
                            <td><?= e(rupiah($transaction['harga_per_kg'] ?? 0)) ?></td>
                            <td class="text-end fw-bold text-heading"><?= e(rupiah($transaction['total'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end mb-4">
                <div class="col-sm-7 col-md-6">
                    <div class="p-3 bg-light rounded-3">
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Total Biaya</span>
                            <strong class="text-heading"><?= e(rupiah($transaction['total'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Telah Dibayar</span>
                            <span class="text-success fw-semibold"><?= e(rupiah($transaction['dibayar'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top mt-1 pt-1">
                            <span class="fw-bold text-heading">Sisa Tagihan</span>
                            <strong class="text-danger fs-6"><?= e(rupiah(max(0, $transaction['total'] - $transaction['dibayar']))) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4 p-3 border rounded-3 bg-white">
                <div class="col-sm-6">
                    <span class="text-muted small d-block mb-1">Status Pengerjaan Cucian:</span>
                    <span class="badge-soft <?= e(status_badge_class($transaction['status_cucian'])) ?>">
                        <i class="bi bi-gear-wide-connected"></i> <?= e($transaction['status_cucian']) ?>
                    </span>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block mb-1">Status Pembayaran:</span>
                    <span class="badge-soft <?= e(status_badge_class($transaction['status_pembayaran'])) ?>">
                        <i class="bi bi-wallet2"></i> <?= e($transaction['status_pembayaran']) ?>
                    </span>
                </div>
            </div>

            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-muted"></i> Rincian Pembayaran Masuk
            </h2>
            <?php if (!$payments): ?>
                <p class="text-muted small mb-0">Belum ada pembayaran yang dicatat.</p>
            <?php else: ?>
                <div class="list-group list-group-flush border rounded-3 mb-4">
                    <?php foreach ($payments as $payment): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                            <div>
                                <span class="fw-semibold text-heading"><?= e($payment['jenis']) ?></span>
                                <span class="text-muted ms-2"><?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?> WIB</span>
                                <?php if ((float) $payment['uang_diterima'] > 0): ?>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        Uang diterima: <?= e(rupiah($payment['uang_diterima'])) ?>
                                        <?php if ((float) $payment['kembalian'] > 0): ?>
                                            · <span class="text-primary fw-semibold">Kembalian: <?= e(rupiah($payment['kembalian'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <strong class="text-success"><?= e(rupiah($payment['jumlah'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted small mt-4 pt-3 border-top">
                <i class="bi bi-heart-fill text-danger me-1"></i> Terima kasih telah mempercayakan pakaian Anda kepada kami!
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
