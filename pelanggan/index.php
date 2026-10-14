<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('pelanggan');
$pdo = getPDO();
// ID pelanggan berasal dari sesi login, jadi pelanggan hanya melihat pesanannya sendiri.
$query = $pdo->prepare(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.berat, t.total, t.status_pembayaran, t.status_cucian,
            d.nama_layanan
     FROM transaksi t LEFT JOIN detail_transaksi d ON d.transaksi_id = t.id
     WHERE t.pelanggan_id = ? ORDER BY t.dibuat_pada DESC LIMIT 20'
);
$query->execute([$_SESSION['user']['id']]);
$transactions = $query->fetchAll();
render_header('Dashboard Pelanggan');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Halo, <?= e($_SESSION['user']['nama']) ?></h1><p class="text-secondary mb-0">Status diperbarui otomatis setiap 15 detik.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(app_url('pelanggan/riwayat.php')) ?>">Lihat semua riwayat</a>
</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Pesanan terbaru</h2></div><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Nota</th><th>Tanggal</th><th>Layanan</th><th>Berat</th><th>Status cucian</th><th>Pembayaran</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $item): ?>
            <tr>
                <td><?= e($item['kode_nota']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($item['dibuat_pada']))) ?></td>
                <td><?= e($item['nama_layanan'] ?? '-') ?></td><td><?= e($item['berat']) ?> kg</td>
                <td><span class="badge <?= e(status_badge_class($item['status_cucian'])) ?>"><?= e($item['status_cucian']) ?></span></td>
                <td><span class="badge <?= e(status_badge_class($item['status_pembayaran'])) ?>"><?= e($item['status_pembayaran']) ?></span></td>
                <td><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('pelanggan/nota.php?id=' . $item['id'])) ?>">Lihat nota</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?><tr><td colspan="7" class="text-center text-secondary py-4">Belum ada transaksi. Nota digital akan muncul setelah pesanan dibuat oleh kasir.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php if ($transactions): ?>
<script>
    // Muat ulang halaman setelah 15 detik agar status terbaru terlihat.
    setTimeout(function () {
        window.location.reload();
    }, 15000);
</script>
<?php endif; ?>
<?php render_footer(); ?>
