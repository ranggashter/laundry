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
$paymentQuery = $pdo->prepare('SELECT jumlah, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id');
$paymentQuery->execute([$id]);
$payments = $paymentQuery->fetchAll();
render_header('Nota Digital');
?>
<div class="receipt">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a class="btn btn-outline-secondary" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">← Kembali ke riwayat</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Cetak nota</button>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="text-center border-bottom pb-4 mb-4">
                <h1 class="h3">Nota Laundry</h1>
                <p class="text-secondary mb-1">Simpan nota ini secara digital atau cetak bila diperlukan.</p>
                <div class="fs-5 fw-semibold"><?= e($transaction['kode_nota']) ?></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-sm-6"><div class="text-secondary small">Pelanggan</div><div class="fw-semibold"><?= e($transaction['nama_pelanggan']) ?></div><div class="small text-secondary"><?= e($transaction['kode_pelanggan'] ?? '') ?></div></div>
                <div class="col-sm-6"><div class="text-secondary small">Tanggal masuk</div><div><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?></div></div>
            </div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Layanan</th><th>Berat</th><th>Harga/kg</th><th class="text-end">Total</th></tr></thead>
                <tbody><tr><td><?= e($transaction['nama_layanan'] ?? '-') ?></td><td><?= e($transaction['berat']) ?> kg</td><td><?= e(rupiah($transaction['harga_per_kg'] ?? 0)) ?></td><td class="text-end"><?= e(rupiah($transaction['total'])) ?></td></tr></tbody>
            </table></div>
            <div class="row justify-content-end"><div class="col-sm-7 col-md-6">
                <div class="d-flex justify-content-between py-1"><span>Total</span><strong><?= e(rupiah($transaction['total'])) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Telah dibayar</span><span><?= e(rupiah($transaction['dibayar'])) ?></span></div>
                <div class="d-flex justify-content-between py-1 border-top mt-1"><span>Sisa tagihan</span><strong><?= e(rupiah(max(0, $transaction['total'] - $transaction['dibayar']))) ?></strong></div>
            </div></div>
            <div class="row g-3 mt-3">
                <div class="col-sm-6"><span class="text-secondary">Status cucian:</span> <span class="badge <?= e(status_badge_class($transaction['status_cucian'])) ?>"><?= e($transaction['status_cucian']) ?></span></div>
                <div class="col-sm-6"><span class="text-secondary">Status pembayaran:</span> <span class="badge <?= e(status_badge_class($transaction['status_pembayaran'])) ?>"><?= e($transaction['status_pembayaran']) ?></span></div>
            </div>
            <hr><h2 class="h6">Pembayaran</h2>
            <?php if (!$payments): ?><p class="text-secondary">Belum ada pembayaran yang dicatat.</p><?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($payments as $payment): ?><li class="list-group-item px-0"><?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?> · <?= e($payment['jenis']) ?> <strong class="float-end"><?= e(rupiah($payment['jumlah'])) ?></strong></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="text-center text-secondary small mt-4 mb-0">Terima kasih telah menggunakan layanan kami.</p>
        </div>
    </div>
</div>
<?php render_footer(); ?>
