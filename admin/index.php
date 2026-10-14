<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');

// Ambil angka ringkasan yang akan ditampilkan pada kartu dashboard.
$pdo = getPDO();
$statistics = [
    'transaksi' => (int) $pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn(),
    'pelanggan' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'")->fetchColumn(),
    'pendapatan' => (float) $pdo->query('SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran')->fetchColumn(),
    'proses' => (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_cucian IN ('Menunggu', 'Diproses')")->fetchColumn(),
];

render_header('Dashboard Admin');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Dashboard Admin</h1><p class="text-secondary mb-0">Ringkasan operasional laundry.</p></div>
</div>
<div class="row g-3">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Semua transaksi</div><div class="display-6 fw-semibold"><?= $statistics['transaksi'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Pelanggan</div><div class="display-6 fw-semibold"><?= $statistics['pelanggan'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Uang diterima</div><div class="h3 mt-2"><?= e(rupiah($statistics['pendapatan'])) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><div class="text-secondary">Cucian berjalan</div><div class="display-6 fw-semibold"><?= $statistics['proses'] ?></div></div></div></div>
</div>
<div class="card border-0 shadow-sm mt-4"><div class="card-body">
    <h2 class="h5">Menu pengelolaan</h2>
    <p class="mb-0">Gunakan halaman <a href="<?= e(app_url('admin/layanan.php')) ?>">Layanan</a> untuk mengatur harga dan durasi, serta <a href="<?= e(app_url('admin/laporan.php')) ?>">Laporan</a> untuk melihat transaksi berdasarkan tanggal.</p>
</div></div>
<?php render_footer(); ?>
