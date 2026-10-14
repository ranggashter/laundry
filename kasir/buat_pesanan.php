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
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon emerald shadow-sm">
            <i class="bi bi-plus-circle-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0">Buat Pesanan Baru</h1>
            <p class="text-muted mb-0 small">Input data cucian pelanggan dan cetak transaksi.</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="card p-3 bg-light border-0 mb-4">
                        <label class="form-label fw-bold text-heading" for="kode-pelanggan">
                            <i class="bi bi-qr-code-scan text-primary"></i> Scan QR / Cari Kode Pelanggan
                        </label>
                        <div class="input-group">
                            <input class="form-control" id="kode-pelanggan" placeholder="Ketik kode, contoh: QR-LAUNDRY-001">
                            <button class="btn btn-outline-primary" type="button" id="scan-pelanggan">
                                <i class="bi bi-search"></i> Pindai Kode
                            </button>
                        </div>
                        <div class="form-text mt-2 text-muted" id="scan-message">Masukkan kode QR pelanggan untuk memilih otomatis.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pelanggan_id">
                            <i class="bi bi-person text-muted"></i> Pilih Pelanggan
                        </label>
                        <select class="form-select" id="pelanggan_id" name="pelanggan_id" required>
                            <option value="">-- Pilih Akun Pelanggan --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= e($customer['id']) ?>" data-code="<?= e($customer['kode_pelanggan']) ?>"><?= e($customer['nama']) ?> (<?= e($customer['kode_pelanggan'] ?: 'tanpa kode') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="layanan_id">
                            <i class="bi bi-tags text-muted"></i> Paket Layanan Cucian
                        </label>
                        <select class="form-select" id="layanan_id" name="layanan_id" required>
                            <option value="">-- Pilih Paket Layanan --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= e($service['id']) ?>" data-price="<?= e($service['harga_per_kg']) ?>"><?= e($service['nama']) ?> (<?= e($service['jenis']) ?>) — <?= e(rupiah($service['harga_per_kg'])) ?>/kg (<?= e($service['durasi_jam']) ?> jam)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="berat">
                            <i class="bi bi-speedometer2 text-muted"></i> Berat Cucian
                        </label>
                        <div class="input-group">
                            <input class="form-control" type="number" id="berat" name="berat" min="0.1" max="500" step="0.01" placeholder="Contoh: 3.5" required>
                            <span class="input-group-text">Kg</span>
                        </div>
                    </div>

                    <div class="card p-3 border-0 bg-primary-subtle text-primary mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block small text-muted">Estimasi Biaya Transaksi</span>
                                <strong class="fs-4" id="perkiraan-total">Rp 0</strong>
                            </div>
                            <i class="bi bi-receipt fs-1 opacity-50"></i>
                        </div>
                        <div class="small text-muted mt-1">Total dihitung ulang dan divalidasi oleh sistem saat disimpan.</div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6" type="submit">
                        <i class="bi bi-check-circle"></i> Simpan Pesanan & Lanjut Pembayaran
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
