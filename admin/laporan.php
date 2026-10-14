<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();

// Filter memakai rentang tanggal inklusif dan parameter terpisah untuk mencegah SQL injection.
$from = $_GET['dari'] ?? date('Y-m-01');
$to = $_GET['sampai'] ?? date('Y-m-d');
$validDate = static function ($value): bool {
    $date = DateTime::createFromFormat('Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === $value;
};
if (!$validDate($from) || !$validDate($to) || $from > $to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}
// Ambil transaksi hanya dalam rentang tanggal yang diminta.
$statement = $pdo->prepare(
    "SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            pelanggan.nama AS nama_pelanggan, kasir.nama AS nama_kasir
     FROM transaksi t
     JOIN users pelanggan ON pelanggan.id = t.pelanggan_id
     JOIN users kasir ON kasir.id = t.kasir_id
     WHERE t.dibuat_pada >= ? AND t.dibuat_pada < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY t.dibuat_pada DESC"
);
$statement->execute([$from, $to]);
$transactions = $statement->fetchAll();
$gross = array_sum(array_column($transactions, 'total'));
// Pembayaran dihitung dari tanggal uang diterima, bukan tanggal pesanan dibuat.
$paymentTotal = $pdo->prepare(
    'SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran
     WHERE dibayar_pada >= ? AND dibayar_pada < DATE_ADD(?, INTERVAL 1 DAY)'
);
$paymentTotal->execute([$from, $to]);
$collected = (float) $paymentTotal->fetchColumn();
render_header('Laporan Transaksi');
?>
<h1 class="h3 mb-4">Laporan Transaksi</h1>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <form class="row gy-2 gx-3 align-items-end" method="get">
        <div class="col-sm-4"><label class="form-label" for="dari">Dari tanggal</label><input class="form-control" type="date" id="dari" name="dari" value="<?= e($from) ?>" required></div>
        <div class="col-sm-4"><label class="form-label" for="sampai">Sampai tanggal</label><input class="form-control" type="date" id="sampai" name="sampai" value="<?= e($to) ?>" required></div>
        <div class="col-sm-4"><button class="btn btn-primary" type="submit">Tampilkan laporan</button></div>
    </form>
</div></div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Jumlah transaksi</div><div class="h3 mb-0"><?= count($transactions) ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Nilai transaksi</div><div class="h3 mb-0"><?= e(rupiah($gross)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Pembayaran diterima pada periode ini</div><div class="h3 mb-0"><?= e(rupiah($collected)) ?></div></div></div></div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Nota</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Berat</th><th>Total</th><th>Dibayar</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $transaction): ?>
            <tr><td><?= e($transaction['kode_nota']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($transaction['dibuat_pada']))) ?></td>
                <td><?= e($transaction['nama_pelanggan']) ?></td><td><?= e($transaction['nama_kasir']) ?></td><td><?= e($transaction['berat']) ?> kg</td>
                <td><?= e(rupiah($transaction['total'])) ?></td><td><?= e(rupiah($transaction['dibayar'])) ?></td><td><?= e($transaction['status_pembayaran']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?><tr><td colspan="8" class="text-center text-secondary py-4">Tidak ada transaksi pada rentang tanggal ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php render_footer(); ?>
