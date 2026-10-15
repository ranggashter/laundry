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
    ?>
    <div class="row justify-content-center py-5">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm p-5 border-0">
                <i class="bi bi-file-earmark-x text-warning fs-1 mb-3"></i>
                <h4 class="fw-bold">Nota Digital Tidak Ditemukan</h4>
                <p class="text-muted small mb-4">Nota yang Anda cari tidak tersedia atau bukan milik akun Anda.</p>
                <a class="btn btn-primary" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">
                    <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
                </a>
            </div>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}
$paymentQuery = $pdo->prepare('SELECT jumlah, uang_diterima, kembalian, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id');
$paymentQuery->execute([$id]);
$payments = $paymentQuery->fetchAll();

$remaining = max(0, (float) $transaction['total'] - (float) $transaction['dibayar']);

render_header('Nota Digital · ' . $transaction['kode_nota']);
?>
<div class="receipt">
    <!-- Action Bar (Hidden on Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">
            <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="button" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- Official Laundry Receipt Card -->
    <div class="card shadow-md border-0 overflow-hidden mb-5">
        <div class="card-body p-4 p-md-5">
            <!-- Brand Header -->
            <div class="text-center border-bottom pb-4 mb-4">
                <div class="brand-badge mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.4rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
                <h1 class="h4 fw-bold text-heading mb-1">Laundry<span class="text-primary-gradient">App</span> Express</h1>
                <p class="text-muted small mb-2">Layanan Cuci & Setrika Bersih, Wangi, Higienis, dan Rapi</p>
                <div class="d-inline-block badge-soft badge-soft-primary px-3 py-2 font-monospace fs-6">
                    <i class="bi bi-receipt me-1"></i> <?= e($transaction['kode_nota']) ?>
                </div>
            </div>

            <!-- Customer & Transaction Info -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <small class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase;">Ditujukan Kepada:</small>
                    <div class="fw-bold text-heading fs-6"><?= e($transaction['nama_pelanggan']) ?></div>
                    <?php if (!empty($transaction['kode_pelanggan'])): ?>
                        <div class="small font-monospace text-primary">ID: <?= e($transaction['kode_pelanggan']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <small class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase;">Waktu Masuk:</small>
                    <div class="fw-semibold text-heading"><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?> WIB</div>
                    <small class="text-muted">Status: <strong><?= e($transaction['status_cucian']) ?></strong></small>
                </div>
            </div>

            <!-- Services Detail Table -->
            <div class="table-responsive mb-4 border rounded-3">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Paket Layanan</th>
                            <th>Berat Cucian</th>
                            <th>Tarif Satuan</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <span class="fw-bold text-heading"><?= e($transaction['nama_layanan'] ?? 'Layanan Laundry') ?></span>
                            </td>
                            <td><?= e($transaction['berat']) ?> kg</td>
                            <td><?= e(rupiah($transaction['harga_per_kg'] ?? 0)) ?> /kg</td>
                            <td class="text-end fw-bold text-heading"><?= e(rupiah($transaction['total'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Calculation Summary -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-6 col-sm-8">
                    <div class="p-3 bg-light rounded-3">
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Total Biaya:</span>
                            <strong class="text-heading"><?= e(rupiah($transaction['total'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Telah Dibayar:</span>
                            <span class="text-success fw-semibold"><?= e(rupiah($transaction['dibayar'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-top mt-1 pt-2">
                            <span class="fw-bold text-heading">Sisa Tagihan:</span>
                            <strong class="<?= $remaining > 0 ? 'text-danger' : 'text-success' ?> fs-6">
                                <?= $remaining > 0 ? e(rupiah($remaining)) : 'LUNAS' ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Indicator Tiles -->
            <div class="row g-3 mb-4 p-3 border rounded-3 bg-white">
                <div class="col-sm-6">
                    <span class="text-muted small d-block mb-1">Status Pengerjaan Cucian:</span>
                    <span class="badge-soft <?= e(status_badge_class($transaction['status_cucian'])) ?>">
                        <i class="bi <?= e(status_icon($transaction['status_cucian'])) ?>"></i> <?= e($transaction['status_cucian']) ?>
                    </span>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block mb-1">Status Pembayaran:</span>
                    <span class="badge-soft <?= e(status_badge_class($transaction['status_pembayaran'])) ?>">
                        <i class="bi bi-wallet2"></i> <?= e($transaction['status_pembayaran']) ?>
                    </span>
                </div>
            </div>

            <!-- Payment Logs -->
            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-muted"></i> Catatan Pembayaran Masuk
            </h2>
            <?php if (!$payments): ?>
                <p class="text-muted small mb-0">Belum ada pembayaran yang tercatat.</p>
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

            <!-- Terms & Footer -->
            <div class="text-center text-muted small mt-4 pt-3 border-top">
                <p class="mb-1">Harap membawa bukti nota ini saat mengambil pakaian. Komplain maksimal 1×24 jam setelah cucian diambil.</p>
                <div class="fw-semibold text-heading">
                    <i class="bi bi-heart-fill text-danger me-1"></i> Terima kasih telah mempercayakan pakaian Anda kepada LaundryApp!
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
