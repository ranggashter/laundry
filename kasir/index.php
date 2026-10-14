<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
// Hitung cucian di setiap tahap dan ambil beberapa pesanan terbaru.
$summary = [
    'baru' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Menunggu'")->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Diproses'")->fetchColumn(),
    'selesai' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian = 'Selesai'")->fetchColumn(),
];
$recent = $pdo->query(
    'SELECT t.id, t.kode_nota, t.dibuat_pada, t.total, t.status_cucian, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id ORDER BY t.id DESC LIMIT 8'
)->fetchAll();
render_header('Dashboard Kasir');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Dashboard Kasir</h1><p class="text-secondary mb-0">Pantau cucian dan transaksi terkini.</p></div>
    <a class="btn btn-primary" href="<?= e(app_url('kasir/buat_pesanan.php')) ?>">+ Buat Pesanan</a>
</div>
<div class="row g-3 mb-4">
    <?php foreach (['baru' => 'Menunggu', 'proses' => 'Diproses', 'selesai' => 'Selesai'] as $key => $label): ?>
        <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary"><?= e($label) ?></div><div class="display-6 fw-semibold"><?= $summary[$key] ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Transaksi terbaru</h2></div><div class="table-responsive">
    <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nota</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Status cucian</th><th></th></tr></thead><tbody>
    <?php foreach ($recent as $item): ?>
        <tr><td><?= e($item['kode_nota']) ?></td><td><?= e($item['nama_pelanggan']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($item['dibuat_pada']))) ?></td><td><?= e(rupiah($item['total'])) ?></td><td><span class="badge <?= e(status_badge_class($item['status_cucian'])) ?>"><?= e($item['status_cucian']) ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('kasir/pembayaran.php?id=' . $item['id'])) ?>">Buka</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada transaksi.</td></tr><?php endif; ?>
    </tbody></table>
</div></div>
<?php render_footer(); ?>
