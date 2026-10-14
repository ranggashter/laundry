<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();
$error = '';
$editing = null;

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
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim($_POST['nama'] ?? '');
        $type = $_POST['jenis'] ?? '';
        $price = filter_var($_POST['harga_per_kg'] ?? null, FILTER_VALIDATE_FLOAT);
        $duration = filter_var($_POST['durasi_jam'] ?? null, FILTER_VALIDATE_INT);
        $active = isset($_POST['aktif']) ? 1 : 0;

        // Periksa data sebelum mengirimnya ke database.
        if ($name === '' || !in_array($type, ['Reguler', 'Express'], true) || $price === false || $price <= 0 || $duration === false || $duration < 1) {
            $error = 'Isi nama, jenis, harga positif, dan durasi minimal satu jam dengan benar.';
        } else {
            if ($id) {
                $save = $pdo->prepare('UPDATE layanan SET nama = ?, jenis = ?, harga_per_kg = ?, durasi_jam = ?, aktif = ? WHERE id = ?');
                $save->execute([$name, $type, $price, $duration, $active, $id]);
            } else {
                $save = $pdo->prepare('INSERT INTO layanan (nama, jenis, harga_per_kg, durasi_jam, aktif) VALUES (?, ?, ?, ?, ?)');
                $save->execute([$name, $type, $price, $duration, $active]);
            }
            redirect('admin/layanan.php');
        }
    }
}

if (isset($_GET['edit'])) {
    // Jika tombol Edit dipilih, isi form dengan data layanan yang dipilih.
    $find = $pdo->prepare('SELECT * FROM layanan WHERE id = ?');
    $find->execute([filter_var($_GET['edit'], FILTER_VALIDATE_INT)]);
    $editing = $find->fetch() ?: null;
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
