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
        <div class="card-body p-4 p-md-5">
            <!-- Header Nota Toko -->
            <div class="text-center border-bottom pb-4 mb-4">
                <div class="brand-badge mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.4rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
                <h1 class="h4 fw-bold mb-0 text-heading">Laundry<span class="text-primary-gradient">App</span></h1>
                <p class="text-muted small mb-2">Layanan Cuci & Setrika Bersih, Rapi, dan Wangi</p>
                <div class="badge-soft badge-soft-primary px-3 py-2 font-monospace fs-6">
                    <i class="bi bi-receipt"></i> <?= e($transaction['kode_nota']) ?>
                </div>
            </div>

            <!-- Informasi Transaksi & Pelanggan -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="text-muted small">Pelanggan</div>
                    <div class="fw-bold text-heading fs-6"><?= e($transaction['nama_pelanggan']) ?></div>
                    <?php if (!empty($transaction['kode_pelanggan'])): ?>
                        <div class="small font-monospace text-muted"><?= e($transaction['kode_pelanggan']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <div class="text-muted small">Waktu Penerimaan</div>
                    <div class="fw-semibold text-heading"><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?> WIB</div>
                    <div class="small text-muted">Kasir: <?= e($transaction['nama_kasir'] ?: 'Petugas Kasir') ?></div>
                </div>
            </div>

            <!-- Tabel Rincian Layanan Cucian -->
            <div class="table-responsive mb-4">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Paket Layanan</th>
                            <th class="text-center">Berat</th>
                            <th class="text-end">Tarif/Kg</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong class="text-heading"><?= e($transaction['nama_layanan'] ?? 'Layanan Laundry') ?></strong>
                            </td>
                            <td class="text-center"><?= e($transaction['berat']) ?> kg</td>
                            <td class="text-end"><?= e(rupiah($transaction['harga_per_kg'] ?? 0)) ?></td>
                            <td class="text-end fw-bold text-heading"><?= e(rupiah($transaction['total'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Ringkasan Biaya & Status -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-7">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Total Biaya Cucian</span>
                            <strong class="text-heading fs-6"><?= e(rupiah($transaction['total'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 text-muted small">
                            <span>Total Telah Dibayar</span>
                            <strong class="text-success"><?= e(rupiah($transaction['dibayar'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top mt-1 pt-2">
                            <span class="fw-bold text-heading">Sisa Tagihan</span>
                            <strong class="<?= $remaining > 0 ? 'text-danger' : 'text-success' ?> fs-6">
                                <?= e(rupiah($remaining)) ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Pembayaran & Cucian -->
            <div class="row g-2 mb-4 p-3 border rounded-3 bg-white">
                <div class="col-6">
                    <span class="text-muted small d-block mb-1">Status Pembayaran:</span>
                    <?php if ($transaction['status_pembayaran'] === 'Lunas'): ?>
                        <span class="badge-soft badge-soft-success fs-6">
                            <i class="bi bi-check-circle-fill"></i> LUNAS
                        </span>
                    <?php else: ?>
                        <span class="badge-soft badge-soft-warning fs-6">
                            <i class="bi bi-hourglass-split"></i> BELUM LUNAS (DP)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <span class="text-muted small d-block mb-1">Status Cucian:</span>
                    <span class="badge-soft <?= e(status_badge_class($transaction['status_cucian'])) ?> fs-6">
                        <i class="bi bi-gear-wide-connected"></i> <?= e($transaction['status_cucian']) ?>
                    </span>
                </div>
            </div>

            <!-- Rincian Catatan Pembayaran (Dengan Uang Diterima & Kembalian) -->
            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-cash-coin text-primary"></i> Rincian Pembayaran Masuk
            </h2>
            <?php if (!$payments): ?>
                <p class="text-muted small mb-0">Belum ada pembayaran yang dicatat untuk nota ini.</p>
            <?php else: ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm border align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis</th>
                                <th>Waktu</th>
                                <th class="text-end">Uang Diterima</th>
                                <th class="text-end">Masuk Tagihan</th>
                                <th class="text-end">Kembalian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold <?= $payment['jenis'] === 'Pelunasan' ? 'text-success' : 'text-warning' ?>">
                                            <?= e($payment['jenis']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?>
                                    </td>
                                    <td class="text-end">
                                        <?= (float) $payment['uang_diterima'] > 0 ? e(rupiah($payment['uang_diterima'])) : e(rupiah($payment['jumlah'])) ?>
                                    </td>
                                    <td class="text-end fw-bold text-heading">
                                        <?= e(rupiah($payment['jumlah'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((float) $payment['kembalian'] > 0): ?>
                                            <span class="text-primary fw-bold"><?= e(rupiah($payment['kembalian'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Catatan Pengambilan Nota -->
            <div class="border-top pt-3 mt-4 text-muted small">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle text-primary me-1"></i> Syarat & Ketentuan Pengambilan:</div>
                <ul class="ps-3 mb-2" style="font-size: 0.8rem; line-height: 1.5;">
                    <li>Harap membawa nota fisik/digital ini saat pengambilan cucian.</li>
                    <li>Sisa tagihan (bila ada) wajib dilunasi saat pengambilan pakaian.</li>
                    <li>Komplain atau klaim hanya dilayani maksimal 1x24 jam sejak pakaian diambil.</li>
                    <li>Barang yang tidak diambil lebih dari 30 hari di luar tanggung jawab pihak laundry.</li>
                </ul>
                <div class="text-center mt-3 pt-2 border-top fw-semibold text-heading" style="font-size: 0.85rem;">
                    <i class="bi bi-heart-fill text-danger me-1"></i> Terima kasih telah mempercayakan pakaian Anda kepada kami!
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
