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
<h1 class="h3 mb-4">Perbarui Status Cucian</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nota</th><th>Pelanggan</th><th>Masuk</th><th>Status sekarang</th><th>Aksi berikutnya</th></tr></thead><tbody>
    <?php foreach ($transactions as $item): ?>
        <?php $next = $nextStatus[$item['status_cucian']] ?? null; ?>
        <tr><td><?= e($item['kode_nota']) ?></td><td><?= e($item['nama_pelanggan']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($item['dibuat_pada']))) ?></td>
            <td><span class="badge <?= e(status_badge_class($item['status_cucian'])) ?>"><?= e($item['status_cucian']) ?></span></td>
            <td>
                <?php if ($next): ?>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($item['id']) ?>"><input type="hidden" name="status" value="<?= e($next) ?>">
                        <button class="btn btn-sm btn-primary" type="submit">Tandai <?= e($next) ?></button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="5" class="text-center text-secondary py-4">Semua cucian sudah diambil.</td></tr><?php endif; ?>
    </tbody></table>
</div></div>
<?php render_footer(); ?>
