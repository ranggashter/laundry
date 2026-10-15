<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_role('admin');
$pdo = getPDO();

$roles = [
    'admin' => 'Administrator',
    'kasir' => 'Kasir',
    'pelanggan' => 'Pelanggan',
];
$error = '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$formValues = [
    'id' => '',
    'nama' => '',
    'username' => '',
    'role' => 'pelanggan',
];
$editing = false;

$generateCustomerCode = static function () use ($pdo): string {
    do {
        $code = 'QR-PLG-' . strtoupper(bin2hex(random_bytes(3)));
        $check = $pdo->prepare('SELECT id FROM users WHERE kode_pelanggan = ? LIMIT 1');
        $check->execute([$code]);
    } while ($check->fetchColumn());

    return $code;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $userId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $find = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
        $find->execute([$userId]);
        $target = $find->fetch();

        if (!$userId || !$target) {
            $error = 'Akun yang akan dihapus tidak ditemukan.';
        } elseif ((int) $target['id'] === (int) $_SESSION['user']['id']) {
            $error = 'Akun yang sedang digunakan tidak dapat dihapus.';
        } elseif ($target['role'] === 'admin' && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() <= 1) {
            $error = 'Admin terakhir tidak dapat dihapus.';
        } else {
            $transactionUsage = $pdo->prepare('SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = ? OR kasir_id = ?');
            $transactionUsage->execute([$userId, $userId]);
            $paymentUsage = $pdo->prepare('SELECT COUNT(*) FROM pembayaran WHERE kasir_id = ?');
            $paymentUsage->execute([$userId]);

            if ((int) $transactionUsage->fetchColumn() > 0 || (int) $paymentUsage->fetchColumn() > 0) {
                $error = 'Akun memiliki riwayat transaksi atau pembayaran, sehingga tidak dapat dihapus.';
            } else {
                $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $delete->execute([$userId]);
                $_SESSION['flash_success'] = 'Akun berhasil dihapus.';
                redirect('admin/users.php');
            }
        }
    } else {
        $idInput = trim((string) ($_POST['id'] ?? ''));
        $userId = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT);
        $formValues = [
            'id' => $idInput,
            'nama' => trim($_POST['nama'] ?? ''),
            'username' => strtolower(trim($_POST['username'] ?? '')),
            'role' => $_POST['role'] ?? '',
        ];
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $editing = $userId !== null && $userId !== false;
        $target = null;

        if ($editing) {
            $find = $pdo->prepare('SELECT id, role, kode_pelanggan FROM users WHERE id = ?');
            $find->execute([$userId]);
            $target = $find->fetch() ?: null;
        }

        if (($idInput !== '' && (!$editing || !$target)) || mb_strlen($formValues['nama']) < 3 || mb_strlen($formValues['nama']) > 100) {
            $error = 'Nama harus berisi 3 sampai 100 karakter dan akun edit harus valid.';
        } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $formValues['username'])) {
            $error = 'Username harus 3 sampai 30 karakter, hanya memakai huruf kecil, angka, atau garis bawah.';
        } elseif (!array_key_exists($formValues['role'], $roles)) {
            $error = 'Pilih peran akun yang valid.';
        } elseif ($editing && (int) $userId === (int) $_SESSION['user']['id'] && $formValues['role'] !== $_SESSION['user']['role']) {
            $error = 'Peran akun yang sedang digunakan tidak dapat diubah.';
        } elseif ($editing && $target['role'] === 'admin' && $formValues['role'] !== 'admin' && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() <= 1) {
            $error = 'Peran admin terakhir tidak dapat diubah.';
        } elseif ((!$editing && strlen($password) < 6) || ($password !== '' && strlen($password) < 6)) {
            $error = 'Kata sandi minimal 6 karakter.';
        } elseif ((!$editing || $password !== '' || $passwordConfirm !== '') && $password !== $passwordConfirm) {
            $error = 'Konfirmasi kata sandi tidak cocok.';
        } else {
            $duplicate = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $duplicate->execute([$formValues['username'], $userId ?? 0]);

            if ($duplicate->fetch()) {
                $error = 'Username tersebut sudah digunakan.';
            } else {
                $customerCode = $formValues['role'] === 'pelanggan'
                    ? (($target && $target['role'] === 'pelanggan' && $target['kode_pelanggan']) ? $target['kode_pelanggan'] : $generateCustomerCode())
                    : null;

                try {
                    if ($editing) {
                        if ($password !== '') {
                            $save = $pdo->prepare('UPDATE users SET nama = ?, username = ?, password = ?, role = ?, kode_pelanggan = ? WHERE id = ?');
                            $save->execute([$formValues['nama'], $formValues['username'], password_hash($password, PASSWORD_DEFAULT), $formValues['role'], $customerCode, $userId]);
                        } else {
                            $save = $pdo->prepare('UPDATE users SET nama = ?, username = ?, role = ?, kode_pelanggan = ? WHERE id = ?');
                            $save->execute([$formValues['nama'], $formValues['username'], $formValues['role'], $customerCode, $userId]);
                        }

                        if ((int) $userId === (int) $_SESSION['user']['id']) {
                            $_SESSION['user']['nama'] = $formValues['nama'];
                            $_SESSION['user']['username'] = $formValues['username'];
                        }
                        $_SESSION['flash_success'] = 'Data akun berhasil diperbarui.';
                    } else {
                        $save = $pdo->prepare('INSERT INTO users (nama, username, password, role, kode_pelanggan) VALUES (?, ?, ?, ?, ?)');
                        $save->execute([$formValues['nama'], $formValues['username'], password_hash($password, PASSWORD_DEFAULT), $formValues['role'], $customerCode]);
                        $_SESSION['flash_success'] = 'Akun baru berhasil dibuat.';
                    }

                    redirect('admin/users.php');
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'Username atau kode pelanggan sudah digunakan. Coba simpan kembali.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $userId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($userId) {
        $find = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = ?');
        $find->execute([$userId]);
        $target = $find->fetch();
        if ($target) {
            $formValues = $target;
            $editing = true;
        } else {
            $error = 'Akun yang dipilih tidak ditemukan.';
        }
    }
}

$users = $pdo->query("SELECT id, nama, username, role, kode_pelanggan, dibuat_pada FROM users ORDER BY FIELD(role, 'admin', 'kasir', 'pelanggan'), nama")->fetchAll();
render_header('Kelola User');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon indigo shadow-sm"><i class="bi bi-people-fill"></i></div>
        <div>
            <h1 class="h3 mb-0 fw-bold text-heading">Kelola User</h1>
            <p class="text-muted mb-0 small">Atur akun administrator, kasir, dan pelanggan.</p>
        </div>
    </div>
    <span class="badge-soft badge-soft-primary px-3 py-2"><i class="bi bi-people me-1"></i> <?= count($users) ?> akun</span>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert"><i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success mb-4 shadow-sm" role="status"><i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?></div>
<?php endif; ?>

<div class="row g-4 align-items-start">
    <div class="col-lg-4">
        <section class="card shadow-sm">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box <?= $editing ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary' ?>">
                        <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-person-plus-fill' ?>"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold mb-0"><?= $editing ? 'Ubah Akun' : 'Tambah User' ?></h2>
                        <small class="text-muted"><?= $editing ? 'Perbarui data akun' : 'Buat akun dengan peran akses' ?></small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= e($formValues['id']) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nama">Nama lengkap</label>
                        <input class="form-control" id="nama" name="nama" value="<?= e($formValues['nama']) ?>" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" value="<?= e($formValues['username']) ?>" minlength="3" maxlength="30" pattern="[a-z0-9_]+" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Peran</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roles as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $formValues['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Akun pelanggan mendapat kode pelanggan otomatis.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Kata sandi<?= $editing ? ' baru (opsional)' : '' ?></label>
                        <input class="form-control" type="password" id="password" name="password" minlength="6" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
                        <?php if ($editing): ?><div class="form-text">Kosongkan jika tidak ingin mengganti kata sandi.</div><?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirm">Konfirmasi kata sandi</label>
                        <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="6" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-check-lg me-1"></i> <?= $editing ? 'Simpan Perubahan' : 'Buat Akun' ?></button>
                        <?php if ($editing): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/users.php')) ?>" title="Batal mengubah akun"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="card shadow-sm">
            <div class="card-header-clean">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-table"></i></div>
                    <div>
                        <h2 class="h6 fw-bold mb-0">Daftar User</h2>
                        <small class="text-muted">Kelola detail akun dan hak akses</small>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Kode Pelanggan</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold text-heading"><?= e($user['nama']) ?></span>
                                <?php if ((int) $user['id'] === (int) $_SESSION['user']['id']): ?><small class="d-block text-muted">Akun Anda</small><?php endif; ?>
                            </td>
                            <td><span class="font-monospace small"><?= e($user['username']) ?></span></td>
                            <td><span class="badge-soft <?= $user['role'] === 'admin' ? 'badge-soft-primary' : ($user['role'] === 'kasir' ? 'badge-soft-info' : 'badge-soft-success') ?>"><?= e($roles[$user['role']] ?? $user['role']) ?></span></td>
                            <td><span class="font-monospace small text-muted"><?= e($user['kode_pelanggan'] ?: '-') ?></span></td>
                            <td>
                                <div class="d-flex justify-content-end gap-1">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin/users.php?edit=' . $user['id'])) ?>" title="Ubah akun"><i class="bi bi-pencil-square"></i></a>
                                    <?php if ((int) $user['id'] !== (int) $_SESSION['user']['id']): ?>
                                        <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($user['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus akun"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada akun user.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php render_footer(); ?>