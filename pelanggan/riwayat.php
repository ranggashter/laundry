<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');
// Ambil seluruh transaksi milik akun yang sedang masuk.
$query = getPDO()->prepare(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.dibayar, t.status_pembayaran, t.status_cucian,
            d.nama_layanan
     FROM transaksi t LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? ORDER BY t.dibuat_pada DESC'
);
$query->execute([$_SESSION['user']['id']]);
$transactions = $query->fetchAll();
render_header('Riwayat Transaksi');
?>
<h1 class="h3 mb-4">Riwayat Transaksi</h1>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nota</th><th>Tanggal</th><th>Layanan</th><th>Berat</th><th>Total</th><th>Terbayar</th><th>Status cucian</th><th></th></tr></thead><tbody>
    <?php foreach ($transactions as $item): ?>
        <tr><td><?= e($item['kode_nota']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($item['dibuat_pada']))) ?></td><td><?= e($item['nama_layanan'] ?? '-') ?></td>
            <td><?= e($item['berat']) ?> kg</td><td><?= e(rupiah($item['total'])) ?></td><td><?= e(rupiah($item['dibayar'])) ?></td>
            <td><span class="badge <?= e(status_badge_class($item['status_cucian'])) ?>"><?= e($item['status_cucian']) ?></span></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('pelanggan/nota.php?id=' . $item['id'])) ?>">Nota digital</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="8" class="text-center text-secondary py-4">Riwayat transaksi masih kosong.</td></tr><?php endif; ?>
    </tbody></table>
</div></div>
<?php render_footer(); ?>
