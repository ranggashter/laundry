<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('kasir');
$pdo = getPDO();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nilai dari form selalu diperiksa di server; hitungan di browser hanya perkiraan.
    verify_csrf();
    $customerId = filter_var($_POST['pelanggan_id'] ?? null, FILTER_VALIDATE_INT);
    $serviceId = filter_var($_POST['layanan_id'] ?? null, FILTER_VALIDATE_INT);
    $weight = filter_var($_POST['berat'] ?? null, FILTER_VALIDATE_FLOAT);

    if (!$customerId || !$serviceId || $weight === false || $weight <= 0 || $weight > 500) {
        $error = 'Pilih pelanggan dan layanan, lalu masukkan berat antara 0 dan 500 kg.';
    } else {
        // Pastikan pelanggan benar-benar pelanggan dan layanan masih aktif.
        $customerQuery = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'pelanggan'");
        $customerQuery->execute([$customerId]);
        $serviceQuery = $pdo->prepare('SELECT id, nama, harga_per_kg FROM layanan WHERE id = ? AND aktif = 1');
        $serviceQuery->execute([$serviceId]);
        $service = $serviceQuery->fetch();

        if (!$customerQuery->fetch() || !$service) {
            $error = 'Pelanggan atau layanan tidak tersedia.';
        } else {
            // Harga akhir berasal dari database supaya harga tidak bisa diubah dari browser.
            $total = round((float) $service['harga_per_kg'] * $weight, 2);
            $invoiceCode = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // Simpan nota beserta rincian dalam satu transaksi agar data selalu konsisten.
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare('INSERT INTO transaksi (kode_nota, pelanggan_id, kasir_id, berat, total) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$invoiceCode, $customerId, $_SESSION['user']['id'], $weight, $total]);
                $transactionId = (int) $pdo->lastInsertId();
                $detail = $pdo->prepare('INSERT INTO detail_transaksi (transaksi_id, layanan_id, nama_layanan, harga_per_kg, berat, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
                $detail->execute([$transactionId, $service['id'], $service['nama'], $service['harga_per_kg'], $weight, $total]);
                $pdo->commit();
                redirect('kasir/pembayaran.php?id=' . $transactionId);
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
        }
    }
}

$customers = $pdo->query("SELECT id, nama, kode_pelanggan FROM users WHERE role = 'pelanggan' ORDER BY nama")->fetchAll();
$services = $pdo->query('SELECT id, nama, jenis, harga_per_kg, durasi_jam FROM layanan WHERE aktif = 1 ORDER BY jenis, nama')->fetchAll();
render_header('Buat Pesanan');
?>
<h1 class="h3 mb-4">Buat Pesanan</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row justify-content-center"><div class="col-lg-8">
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <form method="post">
            <?= csrf_field() ?>
            <label class="form-label" for="kode-pelanggan">Simulasi scan QR pelanggan</label>
            <div class="input-group mb-1">
                <input class="form-control" id="kode-pelanggan" placeholder="Contoh: QR-LAUNDRY-001">
                <button class="btn btn-outline-primary" type="button" id="scan-pelanggan">Pindai kode</button>
            </div>
            <div class="form-text mb-3" id="scan-message">Masukkan kode QR pelanggan, lalu tekan Pindai kode.</div>
            <div class="mb-3">
                <label class="form-label" for="pelanggan_id">Pelanggan</label>
                <select class="form-select" id="pelanggan_id" name="pelanggan_id" required>
                    <option value="">Pilih pelanggan</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= e($customer['id']) ?>" data-code="<?= e($customer['kode_pelanggan']) ?>"><?= e($customer['nama']) ?> (<?= e($customer['kode_pelanggan'] ?: 'tanpa kode') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="layanan_id">Layanan</label>
                <select class="form-select" id="layanan_id" name="layanan_id" required>
                    <option value="">Pilih layanan</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= e($service['id']) ?>" data-price="<?= e($service['harga_per_kg']) ?>"><?= e($service['nama']) ?> — <?= e(rupiah($service['harga_per_kg'])) ?>/kg, <?= e($service['durasi_jam']) ?> jam</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" for="berat">Berat cucian (kg)</label><input class="form-control" type="number" id="berat" name="berat" min="0.1" max="500" step="0.1" required></div>
            <div class="alert alert-primary">Perkiraan total: <strong id="perkiraan-total">Rp 0</strong><div class="small">Total dihitung ulang dan divalidasi oleh server saat pesanan disimpan.</div></div>
            <button class="btn btn-primary" type="submit">Simpan pesanan</button>
        </form>
    </div></div>
</div></div>
<?php render_footer(); ?>
