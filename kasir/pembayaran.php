<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$error = '';
$transactionId = filter_var($_GET['id'] ?? $_POST['transaksi_id'] ?? null, FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek input pembayaran sebelum mengubah tagihan.
    verify_csrf();
    $amount = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_FLOAT);
    if (!$transactionId || $amount === false || $amount <= 0) {
        $error = 'Pilih transaksi dan masukkan jumlah pembayaran yang valid.';
    } else {
        // Proses catatan pembayaran dan total dibayar sebagai satu kesatuan.
        $pdo->beginTransaction();
        try {
            // Kunci baris transaksi agar dua pembayaran bersamaan tidak melampaui sisa tagihan.
            $lock = $pdo->prepare('SELECT total, dibayar FROM transaksi WHERE id = ? FOR UPDATE');
            $lock->execute([$transactionId]);
            $transaction = $lock->fetch();
            if (!$transaction) {
                $error = 'Transaksi tidak ditemukan.';
            } else {
                $remaining = (float) $transaction['total'] - (float) $transaction['dibayar'];
                if ($amount > $remaining) {
                    $error = 'Jumlah pembayaran melebihi sisa tagihan ' . rupiah($remaining) . '.';
                } else {
                    $kind = $amount >= $remaining ? 'Pelunasan' : 'DP';
                    $payment = $pdo->prepare('INSERT INTO pembayaran (transaksi_id, kasir_id, jumlah, jenis) VALUES (?, ?, ?, ?)');
                    $payment->execute([$transactionId, $_SESSION['user']['id'], $amount, $kind]);
                    $paid = (float) $transaction['dibayar'] + $amount;
                    $newStatus = $paid >= (float) $transaction['total'] ? 'Lunas' : 'Belum Lunas';
                    $update = $pdo->prepare('UPDATE transaksi SET dibayar = ?, status_pembayaran = ? WHERE id = ?');
                    $update->execute([$paid, $newStatus, $transactionId]);
                    $pdo->commit();
                    redirect('kasir/pembayaran.php?id=' . $transactionId);
                }
            }
            $pdo->rollBack();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}

$pending = $pdo->query(
    "SELECT t.id, t.kode_nota, t.total, t.dibayar, u.nama AS nama_pelanggan
     FROM transaksi t JOIN users u ON u.id = t.pelanggan_id
     WHERE t.status_pembayaran = 'Belum Lunas' ORDER BY t.dibuat_pada DESC"
)->fetchAll();
// Jika ada ID di URL, tampilkan detail nota dan riwayat pembayarannya.
$current = null;
$payments = [];
if ($transactionId) {
    $find = $pdo->prepare('SELECT t.*, u.nama AS nama_pelanggan FROM transaksi t JOIN users u ON u.id = t.pelanggan_id WHERE t.id = ?');
    $find->execute([$transactionId]);
    $current = $find->fetch() ?: null;
    if ($current) {
        $history = $pdo->prepare('SELECT jumlah, jenis, dibayar_pada FROM pembayaran WHERE transaksi_id = ? ORDER BY id DESC');
        $history->execute([$transactionId]);
        $payments = $history->fetchAll();
    }
}
render_header('Pembayaran');
?>
<h1 class="h3 mb-4">Catat Pembayaran</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5">Pilih transaksi belum lunas</h2>
            <form method="get">
                <label class="form-label" for="pilih-id">Nota</label>
                <select class="form-select mb-3" id="pilih-id" name="id" required>
                    <option value="">Pilih transaksi</option>
                    <?php foreach ($pending as $item): ?>
                        <option value="<?= e($item['id']) ?>" <?= (int) $transactionId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['kode_nota']) ?> — <?= e($item['nama_pelanggan']) ?> (sisa <?= e(rupiah($item['total'] - $item['dibayar'])) ?>)</option>
                    <?php endforeach; ?>
                    <?php if ($current && $current['status_pembayaran'] === 'Lunas'): ?><option value="<?= e($current['id']) ?>" selected><?= e($current['kode_nota']) ?> — sudah lunas</option><?php endif; ?>
                </select>
                <button class="btn btn-outline-primary" type="submit">Buka transaksi</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <?php if ($current): ?>
            <?php $remaining = max(0, (float) $current['total'] - (float) $current['dibayar']); ?>
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h5">Nota <?= e($current['kode_nota']) ?></h2>
                <p class="mb-1">Pelanggan: <?= e($current['nama_pelanggan']) ?></p>
                <p class="mb-1">Total: <strong><?= e(rupiah($current['total'])) ?></strong></p>
                <p class="mb-3">Dibayar: <?= e(rupiah($current['dibayar'])) ?> · Sisa: <strong><?= e(rupiah($remaining)) ?></strong></p>
                <?php if ($remaining > 0): ?>
                    <form method="post" class="row g-2 align-items-end">
                        <?= csrf_field() ?><input type="hidden" name="transaksi_id" value="<?= e($current['id']) ?>">
                        <div class="col"><label class="form-label" for="jumlah">Jumlah pembayaran (Rp)</label><input class="form-control" type="number" id="jumlah" name="jumlah" min="1" max="<?= e($remaining) ?>" step="1" required></div>
                        <div class="col-auto"><button class="btn btn-primary" type="submit">Catat pembayaran</button></div>
                    </form>
                <?php else: ?><div class="alert alert-success mb-0">Tagihan sudah lunas.</div><?php endif; ?>
                <hr><h3 class="h6">Riwayat pembayaran</h3>
                <?php if (!$payments): ?><p class="text-secondary mb-0">Belum ada pembayaran.</p><?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($payments as $payment): ?><li class="list-group-item px-0"><?= e($payment['jenis']) ?> — <?= e(rupiah($payment['jumlah'])) ?> <span class="text-secondary float-end"><?= e(date('d/m/Y H:i', strtotime($payment['dibayar_pada']))) ?></span></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div></div>
        <?php else: ?><div class="alert alert-info">Pilih transaksi untuk melihat sisa tagihan dan mencatat pembayaran.</div><?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
