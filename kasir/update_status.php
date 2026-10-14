<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$error = '';
// Urutan status yang diperbolehkan: Menunggu -> Diproses -> Selesai -> Diambil.
$nextStatus = ['Menunggu' => 'Diproses', 'Diproses' => 'Selesai', 'Selesai' => 'Diambil'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $find = $pdo->prepare('SELECT status_cucian FROM transaksi WHERE id = ?');
    $find->execute([$id]);
    $currentStatus = $find->fetchColumn();

    // Terima hanya perubahan ke tahap berikutnya, bukan lompatan status.
    if (!$id || !$currentStatus || !isset($nextStatus[$currentStatus]) || $nextStatus[$currentStatus] !== $status) {
        $error = 'Perubahan status tidak valid. Status hanya bisa maju satu tahap.';
    } else {
        $update = $pdo->prepare('UPDATE transaksi SET status_cucian = ? WHERE id = ? AND status_cucian = ?');
        $update->execute([$status, $id, $currentStatus]);
        redirect('kasir/update_status.php');
    }
}
$transactions = $pdo->query(
    "SELECT t.id, t.kode_nota, t.status_cucian, t.dibuat_pada, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id
     WHERE t.status_cucian <> 'Diambil' ORDER BY t.dibuat_pada ASC"
)->fetchAll();
render_header('Perbarui Status Cucian');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon sky shadow-sm">
            <i class="bi bi-arrow-repeat"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Perbarui Status Cucian</h1>
            <p class="text-muted mb-0 small">Ubah tahapan cucian pelanggan: Menunggu ➔ Diproses ➔ Selesai ➔ Diambil.</p>
        </div>
    </div>
    <span class="badge-soft badge-soft-info px-3 py-2">
        <i class="bi bi-hourglass-split me-1"></i> <?= count($transactions) ?> Cucian Aktif
    </span>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-list-check text-primary"></i>
            <h2 class="h6 fw-bold mb-0">Antrean Proses Cucian</h2>
        </div>
        <span class="text-muted small">Diurutkan berdasarkan waktu masuk</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Pelanggan</th>
                    <th>Waktu Masuk</th>
                    <th>Status Sekarang</th>
                    <th class="text-end">Aksi Berikutnya</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $item): ?>
                <?php $next = $nextStatus[$item['status_cucian']] ?? null; ?>
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary"><?= e($item['kode_nota']) ?></span>
                    </td>
                    <td>
                        <div class="fw-semibold text-heading"><?= e($item['nama_pelanggan']) ?></div>
                    </td>
                    <td>
                        <div class="text-heading"><?= e(date('d/m/Y', strtotime($item['dibuat_pada']))) ?></div>
                        <div class="text-muted small"><?= e(date('H:i', strtotime($item['dibuat_pada']))) ?> WIB</div>
                    </td>
                    <td>
                        <span class="badge-soft <?= e(status_badge_class($item['status_cucian'])) ?>">
                            <?= e($item['status_cucian']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <?php if ($next): ?>
                            <form method="post" class="d-inline-block">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <input type="hidden" name="status" value="<?= e($next) ?>">
                                <button class="btn btn-sm btn-primary" type="submit">
                                    <i class="bi bi-arrow-right-circle"></i> Tandai <?= e($next) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">Selesai</span>
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
                            <h6 class="fw-bold">Semua Cucian Selesai Diambil</h6>
                            <p class="text-muted small mb-0">Tidak ada cucian yang sedang menunggu, diproses, atau menunggu diambil.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
