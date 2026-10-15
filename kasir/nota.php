<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_roles(['kasir', 'admin']);

$pdo = getPDO();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    redirect('kasir/pembayaran.php');
}

$query = $pdo->prepare(
    'SELECT t.*, u.nama AS nama_pelanggan, u.kode_pelanggan, k.nama AS nama_kasir, 
            d.nama_layanan, d.harga_per_kg
     FROM transaksi t
     JOIN users u ON u.id = t.pelanggan_id
     LEFT JOIN users k ON k.id = t.kasir_id
     LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.id = ?'
);
$query->execute([$id]);
$transaction = $query->fetch();

if (!$transaction) {
    http_response_code(404);
    render_header('Nota Tidak Ditemukan');
    echo '<div class="alert alert-warning">Nota tidak ditemukan. <a href="' . e(app_url('kasir/pembayaran.php')) . '" class="alert-link">Kembali</a></div>';
    render_footer();
    exit;
}

$paymentQuery = $pdo->prepare(
    'SELECT p.*, k.nama AS nama_kasir_bayar 
     FROM pembayaran p 
     LEFT JOIN users k ON k.id = p.kasir_id 
     WHERE p.transaksi_id = ? 
     ORDER BY p.id ASC'
);
$paymentQuery->execute([$id]);
$payments = $paymentQuery->fetchAll();

$lastPayment = $_SESSION['last_payment'] ?? null;
if ($lastPayment && (int) ($lastPayment['transaction_id'] ?? 0) === (int) $id) {
    unset($_SESSION['last_payment']);
} else {
    $lastPayment = null;
}

$remaining = max(0, (float) $transaction['total'] - (float) $transaction['dibayar']);

render_header('Nota ' . $transaction['kode_nota']);
?>

<div class="receipt">
    <!-- Navigasi & Aksi Tombol (Hanya di layar, tersembunyi saat dicetak) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(app_url('kasir/pembayaran.php?id=' . $transaction['id'])) ?>">
                <i class="bi bi-arrow-left"></i> Kembali ke Kasir
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('kasir/buat_pesanan.php')) ?>">
                <i class="bi bi-plus-circle"></i> Pesanan Baru
            </a>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary btn-sm px-3 shadow-sm" type="button" onclick="window.print()">
                <i class="bi bi-printer-fill fs-6"></i> <strong>Cetak Nota (Print)</strong>
            </button>
        </div>
    </div>

    <!-- Banner Notifikasi Sukses Pembayaran Baru -->
    <?php if ($lastPayment): ?>
        <div class="alert alert-success shadow-sm mb-4 border-0 p-3 no-print" role="alert">
            <div class="d-flex align-items-start gap-3 w-100">
                <div class="stat-icon emerald flex-shrink-0" style="width: 38px; height: 38px; font-size: 1.1rem; border-radius: 10px;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading fw-bold mb-1 fs-6">
                        <?= $lastPayment['kind'] === 'DP' ? 'Pembayaran Uang Muka (DP) Berhasil Dicatat' : 'Pembayaran Lunas Berhasil Dicatat' ?>
                    </h5>
                    <div class="row g-2 mt-1 small">
                        <div class="col-sm-4">
                            <span class="text-muted d-block">Uang Diterima:</span>
                            <strong class="text-heading"><?= e(rupiah($lastPayment['tendered'])) ?></strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted d-block">Masuk Tagihan:</span>
                            <strong class="text-success"><?= e(rupiah($lastPayment['amount'])) ?></strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted d-block">Kembalian:</span>
                            <?php if ($lastPayment['change'] > 0): ?>
                                <strong class="text-primary fs-6"><?= e(rupiah($lastPayment['change'])) ?></strong>
                            <?php else: ?>
                                <span class="text-muted">Rp 0 (Uang Pas / DP)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Kartu Nota Fisik / Printable Receipt -->
    <div class="card shadow-md border-0 printable-receipt">
        <div class="card-body p-3 p-md-4">
            <!-- Header Nota Toko -->
            <div class="text-center border-bottom pb-2 mb-2">
                <div class="brand-badge mx-auto mb-1" style="width: 40px; height: 40px; font-size: 1.2rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
                <h1 class="h4 fw-bold text-heading mb-1">Laundry<span class="text-primary-gradient">App</span> Express</h1>
                <p class="text-muted small mb-2">Layanan Cuci & Setrika Bersih, Wangi, Higienis, dan Rapi</p>
                <div class="badge-soft badge-soft-primary px-2 py-1 font-monospace">
                    <i class="bi bi-receipt"></i> <?= e($transaction['kode_nota']) ?>
                </div>
            </div>

            <!-- Informasi Transaksi & Pelanggan -->
            <div class="row g-2 mb-2">
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

            <!-- Tabel Rincian Layanan Cucian -->
            <div class="table-responsive mb-2 border rounded-3">
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
                                <strong class="text-heading"><?= e($transaction['nama_layanan'] ?? 'Layanan Laundry') ?></strong>
                            </td>
                            <td><?= e($transaction['berat']) ?> kg</td>
                            <td><?= e(rupiah($transaction['harga_per_kg'] ?? 0)) ?> /kg</td>
                            <td class="text-end fw-bold text-heading"><?= e(rupiah($transaction['total'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Ringkasan Biaya & Status -->
            <div class="row align-items-center g-2 mb-2">
                <div class="col-3 print-qr">
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
                        <div id="transactionQrCode" class="nota-qr" data-qr-code="<?= e('NOTA:' . $transaction['kode_nota']) ?>" role="img" aria-label="QR kode nota"></div>
                        <div class="small text-muted">QR nota<br><span class="font-monospace fw-semibold"><?= e($transaction['kode_nota']) ?></span></div>
                    </div>
                </div>
                <div class="col-9">
                    <div class="p-2 bg-light rounded-3">
                        <div class="d-flex justify-content-between py-0 text-muted small">
                            <span>Total Biaya:</span>
                            <strong class="text-heading"><?= e(rupiah($transaction['total'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-0 text-muted small">
                            <span>Telah Dibayar:</span>
                            <span class="text-success fw-semibold"><?= e(rupiah($transaction['dibayar'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top mt-1 pt-1">
                            <span class="fw-bold text-heading">Sisa Tagihan:</span>
                            <strong class="<?= $remaining > 0 ? 'text-danger' : 'text-success' ?> fs-6">
                                <?= $remaining > 0 ? e(rupiah($remaining)) : 'LUNAS' ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Pembayaran & Cucian -->
            <div class="row g-2 mb-2 p-2 border rounded-3 bg-white">
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

            <!-- Rincian Catatan Pembayaran (Dengan Uang Diterima & Kembalian) -->
            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2 print-omit">
                <i class="bi bi-cash-coin text-primary"></i> Catatan Pembayaran Masuk
            </h2>
            <?php if (!$payments): ?>
                <p class="text-muted small mb-0 print-omit">Belum ada pembayaran yang tercatat.</p>
            <?php else: ?>
                <div class="list-group list-group-flush border rounded-3 mb-4 print-omit">
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

            <!-- Catatan Pengambilan Nota -->
            <div class="border-top pt-3 mt-4 text-muted small print-omit">
                <p class="mb-1">Harap membawa bukti nota ini saat mengambil pakaian. Komplain maksimal 1×24 jam setelah cucian diambil.</p>
                <div class="text-center mt-3 pt-2 border-top fw-semibold text-heading" style="font-size: 0.85rem;">
                    <i class="bi bi-heart-fill text-danger me-1"></i> Terima kasih telah mempercayakan pakaian Anda kepada LaundryApp!
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php render_footer(); ?>
