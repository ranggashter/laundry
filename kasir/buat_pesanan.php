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
        $error = 'Pilih pelanggan dan paket layanan, lalu masukkan berat cucian antara 0.1 dan 500 kg.';
    } else {
        // Pastikan pelanggan benar-benar pelanggan dan layanan masih aktif.
        $customerQuery = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'pelanggan'");
        $customerQuery->execute([$customerId]);
        $serviceQuery = $pdo->prepare('SELECT id, nama, harga_per_kg FROM layanan WHERE id = ? AND aktif = 1');
        $serviceQuery->execute([$serviceId]);
        $service = $serviceQuery->fetch();

        if (!$customerQuery->fetch() || !$service) {
            $error = 'Pelanggan atau layanan tidak ditemukan atau sudah nonaktif.';
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
render_header('Buat Pesanan Baru');
?>
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon emerald shadow-sm">
            <i class="bi bi-plus-circle-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Buat Pesanan Laundry Baru</h1>
            <p class="text-muted mb-0 small">Input data penerimaan cucian pelanggan dan cetak nota transaksi.</p>
        </div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(app_url('kasir/index.php')) ?>">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Input Form Column -->
        <div class="col-lg-7">
            <!-- QR Simulation Box -->
            <div class="card shadow-sm border-0 mb-4 bg-light">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-qr-code-scan text-primary fs-5"></i>
                        <h2 class="h6 fw-bold mb-0 text-heading">Pindai / Cari Kode QR Pelanggan</h2>
                    </div>
                    <p class="text-muted small mb-3">Tanyakan kartu/kode QR milik pelanggan untuk auto-select akun pelanggan dengan cepat.</p>
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        <input class="form-control" id="kode-pelanggan" placeholder="Ketik kode, contoh: QR-LAUNDRY-001">
                        <button class="btn btn-primary" type="button" id="scan-pelanggan">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                    <button class="btn btn-sm btn-outline-primary mb-2" type="button" id="scan-camera-pelanggan">
                        <i class="bi bi-camera-video"></i> <span data-scan-label>Scan Kamera</span>
                    </button>
                    <div class="mb-2" id="qr-reader-pelanggan" hidden></div>
                    <div class="small text-muted" id="scan-message">
                        Kode demo tersedia: <code role="button" class="text-primary fw-bold" onclick="document.querySelector('#kode-pelanggan').value='QR-LAUNDRY-001'; document.querySelector('#scan-pelanggan').click();">QR-LAUNDRY-001</code> (Klik untuk coba).
                    </div>
                </div>
            </div>

            <!-- Main Order Form Card -->
            <div class="card shadow-sm">
                <div class="card-header-clean">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-box bg-primary-subtle text-primary">
                            <i class="bi bi-basket"></i>
                        </div>
                        <div>
                            <h2 class="h6 fw-bold mb-0">Rincian Cucian</h2>
                            <small class="text-muted">Pilih paket dan timbang berat pakaian</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label" for="pelanggan_id">
                            <i class="bi bi-person text-muted"></i> Pilih Pelanggan <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="pelanggan_id" name="pelanggan_id" required>
                            <option value="">-- Pilih Akun Pelanggan --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= e($customer['id']) ?>" data-code="<?= e($customer['kode_pelanggan']) ?>">
                                    <?= e($customer['nama']) ?> <?= $customer['kode_pelanggan'] ? '· (' . e($customer['kode_pelanggan']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="layanan_id">
                            <i class="bi bi-tags text-muted"></i> Paket Layanan Cucian <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="layanan_id" name="layanan_id" required>
                            <option value="">-- Pilih Paket Layanan --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= e($service['id']) ?>" data-price="<?= e($service['harga_per_kg']) ?>">
                                    <?= e($service['nama']) ?> (<?= e($service['jenis']) ?>) — <?= e(rupiah($service['harga_per_kg'])) ?>/kg · <?= e($service['durasi_jam']) ?> jam
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="berat">
                            <i class="bi bi-speedometer2 text-muted"></i> Berat Timbangan Cucian (kg) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group mb-2">
                            <input class="form-control form-control-lg" type="number" id="berat" name="berat" min="0.1" max="500" step="0.01" placeholder="Contoh: 3.5" required>
                            <span class="input-group-text fw-bold">Kg</span>
                        </div>
                        <!-- Quick Weight Presets -->
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="text-muted small me-1">Tambah cepat:</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-weight py-1 px-2" data-add="1">+1 kg</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-weight py-1 px-2" data-add="2">+2 kg</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-weight py-1 px-2" data-add="3">+3 kg</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-weight py-1 px-2" data-add="5">+5 kg</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-weight py-1 px-2" data-add="10">+10 kg</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Cost Summary Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header-clean">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-box bg-success-subtle text-success">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <h2 class="h6 fw-bold mb-0">Estimasi Biaya</h2>
                            <small class="text-muted">Kalkulasi otomatis real-time</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="p-4 bg-light rounded-4 text-center mb-4">
                        <span class="text-muted small d-block mb-1">Perkiraan Total Biaya</span>
                        <div class="h2 fw-bold text-primary mb-1" id="perkiraan-total">Rp 0</div>
                        <div class="text-muted small" id="preview-detail-text">Pilih paket dan tentukan berat</div>
                    </div>

                    <div class="list-group list-group-flush border rounded-3 mb-4">
                        <div class="list-group-item d-flex align-items-center gap-2 py-2 px-3 small text-muted">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Kode nota otomatis dibuat sistem</span>
                        </div>
                        <div class="list-group-item d-flex align-items-center gap-2 py-2 px-3 small text-muted">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Status pengerjaan awal: <strong>Menunggu</strong></span>
                        </div>
                        <div class="list-group-item d-flex align-items-center gap-2 py-2 px-3 small text-muted">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Langsung diarahkan ke modul pembayaran DP/Lunas</span>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6 shadow-sm" type="submit">
                        <i class="bi bi-arrow-right-circle me-1"></i> Simpan & Lanjut Pembayaran
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<?php render_footer(); ?>
