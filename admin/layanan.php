<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();
$error = '';
$priceError = false;
$editing = null;
$formValues = [
    'id' => '',
    'nama' => '',
    'jenis' => 'Reguler',
    'harga_per_kg' => '',
    'durasi_jam' => '',
    'aktif' => 1,
];

// Saat tombol Edit dipilih, isi form dengan data layanan yang sudah tersimpan.
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId) {
        $find = $pdo->prepare('SELECT * FROM layanan WHERE id = ?');
        $find->execute([$editId]);
        $editing = $find->fetch() ?: null;

        if ($editing) {
            $formValues = $editing;
            // Format angka tanpa desimal berlebih jika ada
            $priceText = (string) $formValues['harga_per_kg'];
            if (strpos($priceText, '.') !== false) {
                $formValues['harga_per_kg'] = rtrim(rtrim($priceText, '0'), '.');
            }
        }
    }
}

// Proses form yang dikirim. Field "action" membedakan hapus dan simpan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        // Layanan yang pernah dipakai transaksi tidak dihapus agar riwayat tetap utuh.
        $usage = $pdo->prepare('SELECT COUNT(*) FROM detail_transaksi WHERE layanan_id = ?');
        $usage->execute([$id]);
        if (!$id || (int) $usage->fetchColumn() > 0) {
            $error = 'Layanan tidak dapat dihapus karena sudah memiliki riwayat transaksi terkait.';
        } else {
            $delete = $pdo->prepare('DELETE FROM layanan WHERE id = ?');
            $delete->execute([$id]);
            redirect('admin/layanan.php');
        }
    } else {
        // Simpan kembali isi form jika ada kesalahan supaya pengguna tidak mengetik ulang.
        $formValues = [
            'id' => $_POST['id'] ?? '',
            'nama' => trim($_POST['nama'] ?? ''),
            'jenis' => $_POST['jenis'] ?? '',
            'harga_per_kg' => trim($_POST['harga_per_kg'] ?? ''),
            'durasi_jam' => trim($_POST['durasi_jam'] ?? ''),
            'aktif' => isset($_POST['aktif']) ? 1 : 0,
        ];

        $id = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        $priceText = $formValues['harga_per_kg'];
        $priceIsNumber = preg_match('/^[0-9]+$/', $priceText) === 1;
        $price = $priceIsNumber ? (int) $priceText : 0;
        $duration = filter_var($formValues['durasi_jam'], FILTER_VALIDATE_INT);
        $active = isset($_POST['aktif']) ? 1 : 0;

        // Harga Rupiah dimasukkan sebagai bilangan bulat, misalnya 7000.
        if ($formValues['nama'] === '') {
            $error = 'Nama paket layanan wajib diisi.';
        } elseif (!in_array($formValues['jenis'], ['Reguler', 'Express'], true)) {
            $error = 'Pilih jenis paket layanan yang valid (Reguler atau Express).';
        } elseif (!$priceIsNumber || $price < 1 || $price > 9999999999) {
            $priceError = true;
            $error = 'Harga harus berupa angka bulat 1 sampai 9.999.999.999 tanpa tanda titik atau Rp. Contoh: ketik 7000.';
        } elseif ($duration === false || $duration < 1) {
            $error = 'Estimasi durasi pengerjaan harus berupa angka jam, minimal 1 jam.';
        } else {
            if ($id) {
                $save = $pdo->prepare('UPDATE layanan SET nama = ?, jenis = ?, harga_per_kg = ?, durasi_jam = ?, aktif = ? WHERE id = ?');
                $save->execute([$formValues['nama'], $formValues['jenis'], $price, $duration, $active, $id]);
            } else {
                $save = $pdo->prepare('INSERT INTO layanan (nama, jenis, harga_per_kg, durasi_jam, aktif) VALUES (?, ?, ?, ?, ?)');
                $save->execute([$formValues['nama'], $formValues['jenis'], $price, $duration, $active]);
            }
            redirect('admin/layanan.php');
        }
    }
}

$services = $pdo->query('SELECT * FROM layanan ORDER BY aktif DESC, jenis, nama')->fetchAll();
render_header('Kelola Layanan');
?>
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm">
            <i class="bi bi-tags-fill"></i>
        </div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Kelola Layanan Laundry</h1>
            <p class="text-muted mb-0 small">Atur daftar paket cucian, penetapan tarif per kilogram, dan durasi estimasi pengerjaan.</p>
        </div>
    </div>
    <div>
        <span class="badge-soft badge-soft-primary px-3 py-2">
            <i class="bi bi-box-seam me-1"></i> <?= count($services) ?> Total Paket
        </span>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Tambah / Edit -->
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box <?= $editing ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary' ?>">
                        <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-plus-lg' ?>"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0"><?= $editing ? 'Edit Layanan' : 'Tambah Layanan Baru' ?></h2>
                        <small class="text-muted"><?= $editing ? 'Perbarui data paket yang dipilih' : 'Tambahkan paket cucian baru' ?></small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($formValues['id']) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="nama">
                            <i class="bi bi-card-text text-muted"></i> Nama Layanan
                        </label>
                        <input class="form-control" id="nama" name="nama" placeholder="Contoh: Cuci Kering Reguler" value="<?= e($formValues['nama']) ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="jenis">
                            <i class="bi bi-lightning-charge text-muted"></i> Kategori Paket
                        </label>
                        <select class="form-select" id="jenis" name="jenis" required>
                            <?php foreach (['Reguler', 'Express'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= $formValues['jenis'] === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?> <?= $type === 'Express' ? '⚡ (Prioritas Kilat)' : '🕒 (Standar Reguler)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="harga_per_kg">
                            <i class="bi bi-cash-stack text-muted"></i> Tarif per kg
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input class="form-control <?= $priceError ? 'is-invalid' : '' ?>" type="number" id="harga_per_kg" name="harga_per_kg" min="1" max="9999999999" step="1" placeholder="7000" value="<?= e($formValues['harga_per_kg']) ?>" required>
                            <span class="input-group-text">/ kg</span>
                        </div>
                        <?php if ($priceError): ?>
                            <div class="invalid-feedback d-block"><?= e($error) ?></div>
                        <?php else: ?>
                            <div class="form-text small text-muted">Masukkan bilangan bulat dari 1 sampai 9.999.999.999, contoh: <code>7000</code>.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="durasi_jam">
                            <i class="bi bi-hourglass-split text-muted"></i> Estimasi Waktu Selesai
                        </label>
                        <div class="input-group">
                            <input class="form-control" type="number" id="durasi_jam" name="durasi_jam" min="1" placeholder="24" value="<?= e($formValues['durasi_jam']) ?>" required>
                            <span class="input-group-text">Jam</span>
                        </div>
                    </div>

                    <div class="card p-3 bg-light border-0 mb-4">
                        <div class="form-check form-switch m-0 d-flex align-items-center justify-content-between ps-0">
                            <label class="form-check-label fw-semibold" for="aktif">
                                Status Aktif
                                <div class="text-muted small fw-normal">Dapat dipilih kasir saat membuat pesanan</div>
                            </label>
                            <input class="form-check-input ms-2" type="checkbox" role="switch" id="aktif" name="aktif" <?= !empty($formValues['aktif']) ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg"></i> <?= $editing ? 'Perbarui Layanan' : 'Simpan Layanan' ?>
                        </button>
                        <?php if ($editing): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/layanan.php')) ?>">
                                <i class="bi bi-x-circle"></i> Batal Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Layanan -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header-clean flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-collection"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Daftar Paket Cucian</h2>
                        <small class="text-muted">Kelola tarif aktif dan nonaktif</small>
                    </div>
                </div>
                <div style="min-width: 220px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Cari nama atau jenis..." data-table-search="#servicesTable">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="servicesTable">
                    <thead>
                        <tr>
                            <th>Nama Paket</th>
                            <th>Kategori</th>
                            <th>Tarif / kg</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon <?= $service['jenis'] === 'Express' ? 'amber' : 'indigo' ?>" style="width: 34px; height: 34px; font-size: 0.95rem; border-radius: 9px;">
                                        <i class="bi <?= $service['jenis'] === 'Express' ? 'bi-lightning-fill' : 'bi-droplet-fill' ?>"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-heading d-block"><?= e($service['nama']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($service['jenis'] === 'Express'): ?>
                                    <span class="badge-soft badge-soft-warning"><i class="bi bi-lightning-charge-fill"></i> Express</span>
                                <?php else: ?>
                                    <span class="badge-soft badge-soft-primary"><i class="bi bi-clock-history"></i> Reguler</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-bold text-heading"><?= e(rupiah($service['harga_per_kg'])) ?></span>
                                <span class="text-muted small">/kg</span>
                            </td>
                            <td>
                                <span class="text-body"><i class="bi bi-stopwatch text-muted me-1"></i><?= e($service['durasi_jam']) ?> jam</span>
                            </td>
                            <td>
                                <?php if ($service['aktif']): ?>
                                    <span class="status-pill active"><span class="status-dot"></span> Aktif</span>
                                <?php else: ?>
                                    <span class="status-pill inactive"><span class="status-dot"></span> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn-action" href="<?= e(app_url('admin/layanan.php?edit=' . $service['id'])) ?>" title="Edit Layanan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form class="d-inline" method="post" onsubmit="return confirm('Hapus layanan <?= e(addslashes($service['nama'])) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($service['id']) ?>">
                                        <button class="btn-action btn-action-danger" type="submit" title="Hapus Layanan">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h6 class="fw-bold">Belum Ada Layanan</h6>
                                    <p class="text-muted small mb-0">Tambahkan layanan cucian baru melalui formulir di sebelah kiri.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
