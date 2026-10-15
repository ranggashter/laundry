<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$error = '';
// Urutan status yang diperbolehkan: Menunggu -> Diproses -> Selesai -> Diambil.
$nextStatus = ['Menunggu' => 'Diproses', 'Diproses' => 'Selesai', 'Selesai' => 'Diambil'];

$actionLabels = [
    'Diproses' => ['label' => 'Mulai Cuci (Diproses)', 'icon' => 'bi-play-circle-fill', 'class' => 'btn-primary'],
    'Selesai' => ['label' => 'Tandai Selesai Cuci', 'icon' => 'bi-check2-circle', 'class' => 'btn-success'],
    'Diambil' => ['label' => 'Serahkan Cucian (Diambil)', 'icon' => 'bi-bag-check-fill', 'class' => 'btn-info text-white'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $find = $pdo->prepare('SELECT status_cucian FROM transaksi WHERE id = ?');
    $find->execute([$id]);
    $currentStatus = $find->fetchColumn();

    // Terima hanya perubahan ke tahap berikutnya, bukan lompatan status.
    if (!$id || !$currentStatus || !isset($nextStatus[$currentStatus]) || $nextStatus[$currentStatus] !== $status) {
        $error = 'Perubahan status tidak valid. Status hanya bisa maju satu tahap secara berurutan.';
    } else {
        $update = $pdo->prepare('UPDATE transaksi SET status_cucian = ? WHERE id = ? AND status_cucian = ?');
        $update->execute([$status, $id, $currentStatus]);
        redirect('kasir/update_status.php');
    }
}

$transactions = $pdo->query(
    "SELECT t.id, t.kode_nota, t.status_cucian, t.dibuat_pada, t.berat, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id
     WHERE t.status_cucian <> 'Diambil' ORDER BY t.dibuat_pada ASC"
)->fetchAll();

render_header('Perbarui Status Cucian');
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon sky shadow-sm">
            <i class="bi bi-arrow-repeat"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Perbarui Tahapan Cucian</h1>
            <p class="text-muted mb-0 small">Kelola alur pengerjaan cucian pelanggan: Menunggu ➔ Diproses ➔ Selesai ➔ Diambil.</p>
        </div>
    </div>
    <span class="badge-soft badge-soft-info px-3 py-2">
        <i class="bi bi-hourglass-split me-1"></i> <?= count($transactions) ?> Antrean Aktif
    </span>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<!-- Workflow Visual Roadmap Card -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge-soft badge-soft-warning"><i class="bi bi-hourglass-split"></i> 1. Menunggu</span>
                <i class="bi bi-chevron-right text-muted small"></i>
                <span class="badge-soft badge-soft-info"><i class="bi bi-arrow-repeat"></i> 2. Diproses</span>
                <i class="bi bi-chevron-right text-muted small"></i>
                <span class="badge-soft badge-soft-success"><i class="bi bi-check2-circle"></i> 3. Selesai</span>
                <i class="bi bi-chevron-right text-muted small"></i>
                <span class="badge-soft badge-soft-secondary"><i class="bi bi-bag-check"></i> 4. Diambil</span>
            </div>
            <small class="text-muted">Klik tombol aksi pada baris nota untuk memajukan status cucian.</small>
        </div>
    </div>
</div>

<!-- Antrean Table -->
<div class="card shadow-sm">
    <div class="card-header-clean flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="icon-box bg-primary-subtle text-primary">
                <i class="bi bi-list-check"></i>
            </div>
            <div>
                <h2 class="h6 fw-bold mb-0">Antrean Pengerjaan Laundry</h2>
                <small class="text-muted">Diurutkan berdasarkan waktu masuk terdahulu</small>
            </div>
        </div>
        <div style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari nota atau pelanggan..." data-table-search="#statusQueueTable">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="statusQueueTable">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Pelanggan</th>
                    <th>Waktu Masuk</th>
                    <th>Status Sekarang</th>
                    <th class="text-end">Aksi Majukan Tahap</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $item): ?>
                <?php 
                    $next = $nextStatus[$item['status_cucian']] ?? null; 
                    $btnMeta = $next ? ($actionLabels[$next] ?? ['label' => "Tandai $next", 'icon' => 'bi-arrow-right', 'class' => 'btn-primary']) : null;
                ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($item['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="fw-semibold text-heading"><?= e($item['nama_pelanggan']) ?></div>
                        <small class="text-muted"><?= e($item['berat']) ?> kg</small>
                    </td>
                    <td>
                        <div class="text-heading small"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($item['status_cucian'])) ?>">
                            <i class="bi <?= e(status_icon($item['status_cucian'])) ?>"></i>
                            <?= e($item['status_cucian']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <?php if ($next && $btnMeta): ?>
                            <form method="post" class="d-inline-block">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <input type="hidden" name="status" value="<?= e($next) ?>">
                                <button class="btn btn-sm <?= e($btnMeta['class']) ?>" type="submit">
                                    <i class="bi <?= e($btnMeta['icon']) ?>"></i> <?= e($btnMeta['label']) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge-soft badge-soft-secondary">Siap Diambil</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-check2-all text-success"></i>
                            </div>
                            <h6 class="fw-bold">Semua Cucian Telah Selesai Diambil!</h6>
                            <p class="text-muted small mb-0">Tidak ada antrean cucian yang tertunda saat ini. Kerja bagus!</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
