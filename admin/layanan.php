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
<?php if ($error && !$priceError): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5"><?= $formValues['id'] ? 'Edit layanan' : 'Tambah layanan' ?></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($formValues['id']) ?>">
                <div class="mb-3"><label class="form-label" for="nama">Nama layanan</label><input class="form-control" id="nama" name="nama" value="<?= e($formValues['nama']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="jenis">Jenis</label>
                    <select class="form-select" id="jenis" name="jenis" required>
                        <?php foreach (['Reguler', 'Express'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= $formValues['jenis'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="harga_per_kg">Harga per kg</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input class="form-control <?= $priceError ? 'is-invalid' : '' ?>" type="number" id="harga_per_kg" name="harga_per_kg" min="1" max="9999999999" step="1" inputmode="numeric" placeholder="Contoh: 7000" value="<?= e($formValues['harga_per_kg']) ?>" aria-describedby="bantuan-harga" required>
                        <span class="input-group-text">/kg</span>
                    </div>
                    <div class="form-text" id="bantuan-harga">Ketik angka saja tanpa titik. Contoh: 7000 berarti Rp 7.000 per kg.</div>
                    <?php if ($priceError): ?><div class="text-danger small mt-1"><?= e($error) ?></div><?php endif; ?>
                </div>
                <div class="mb-3"><label class="form-label" for="durasi_jam">Durasi (jam)</label><input class="form-control" type="number" id="durasi_jam" name="durasi_jam" min="1" value="<?= e($formValues['durasi_jam']) ?>" required></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $formValues['aktif'] ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Layanan aktif</label></div>
                <button class="btn btn-primary" type="submit">Simpan layanan</button>
                <?php if ($formValues['id']): ?><a class="btn btn-outline-secondary" href="<?= e(app_url('admin/layanan.php')) ?>">Batal</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Nama</th><th>Jenis</th><th>Harga/kg</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= e($service['nama']) ?></td><td><?= e($service['jenis']) ?></td><td><?= e(rupiah($service['harga_per_kg'])) ?></td>
                        <td><?= e($service['durasi_jam']) ?> jam</td><td><?= $service['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin/layanan.php?edit=' . $service['id'])) ?>">Edit</a>
                            <form class="d-inline" method="post" onsubmit="return confirm('Hapus layanan ini?')">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($service['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$services): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada layanan.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
<?php render_footer(); ?>
