<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();
$error = '';
$priceError = false;
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
            // MySQL bisa menampilkan harga bulat sebagai "7000.00"; form cukup menampilkan "7000".
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
            $error = 'Layanan tidak dapat dihapus karena sudah dipakai transaksi.';
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
            $error = 'Nama layanan wajib diisi.';
        } elseif (!in_array($formValues['jenis'], ['Reguler', 'Express'], true)) {
            $error = 'Pilih jenis layanan Reguler atau Express.';
        } elseif (!$priceIsNumber || $price < 1 || $price > 9999999999) {
            $priceError = true;
            $error = 'Harga harus berupa angka bulat 1 sampai 9.999.999.999. Contoh: ketik 7000 untuk Rp 7.000, tanpa titik atau tulisan Rp.';
        } elseif ($duration === false || $duration < 1) {
            $error = 'Durasi harus berupa angka jam, minimal 1.';
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

$services = $pdo->query('SELECT * FROM layanan ORDER BY jenis, nama')->fetchAll();
render_header('Kelola Layanan');
?>
<h1 class="h3 mb-4">Kelola Layanan</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5"><?= $editing ? 'Edit layanan' : 'Tambah layanan' ?></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
                <div class="mb-3"><label class="form-label" for="nama">Nama layanan</label><input class="form-control" id="nama" name="nama" value="<?= e($editing['nama'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label" for="jenis">Jenis</label>
                    <select class="form-select" id="jenis" name="jenis" required>
                        <?php foreach (['Reguler', 'Express'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= ($editing['jenis'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label" for="harga_per_kg">Harga per kg (Rp)</label><input class="form-control" type="number" id="harga_per_kg" name="harga_per_kg" min="1" step="100" value="<?= e($editing['harga_per_kg'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label" for="durasi_jam">Durasi (jam)</label><input class="form-control" type="number" id="durasi_jam" name="durasi_jam" min="1" value="<?= e($editing['durasi_jam'] ?? '') ?>" required></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= !isset($editing['aktif']) || $editing['aktif'] ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Layanan aktif</label></div>
                <button class="btn btn-primary" type="submit">Simpan layanan</button>
                <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(app_url('admin/layanan.php')) ?>">Batal</a><?php endif; ?>
            </form>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-collection text-primary"></i>
                    <h2 class="h6 fw-bold mb-0">Daftar Paket Cucian Tersedia</h2>
                </div>
                <span class="text-muted small">Diurutkan berdasarkan jenis</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Layanan</th>
                            <th>Kategori</th>
                            <th>Harga / kg</th>
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
