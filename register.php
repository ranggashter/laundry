<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

// Jika pengguna sudah login, langsung alihkan ke dashboard perannya
if (!empty($_SESSION['user'])) {
    redirect(role_home($_SESSION['user']['role']));
}

$pdo = getPDO();
$error = '';
$nama = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Pendaftaran publik selalu ditujukan untuk pelanggan
    $role = 'pelanggan';

    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $error = 'Nama lengkap harus antara 3 hingga 100 karakter.';
    } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $error = 'Username hanya boleh berisi huruf kecil, angka, dan garis bawah (_) sebanyak 3–30 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Konfirmasi kata sandi tidak cocok dengan kata sandi.';
    } else {
        // Cek apakah username sudah terdaftar
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Username "' . e($username) . '" sudah digunakan. Silakan pilih username lain.';
        } else {
            // Setiap pelanggan otomatis mendapatkan kode QR unik untuk titip cucian
            do {
                $kodePelanggan = 'QR-PLG-' . strtoupper(bin2hex(random_bytes(3)));
                $stmtCode = $pdo->prepare('SELECT id FROM users WHERE kode_pelanggan = ? LIMIT 1');
                $stmtCode->execute([$kodePelanggan]);
            } while ($stmtCode->fetch());

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (nama, username, password, role, kode_pelanggan) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$nama, $username, $hashedPassword, $role, $kodePelanggan]);

            $_SESSION['flash_success'] = 'Pendaftaran berhasil! Akun pelanggan Anda telah aktif. Kode QR Pelanggan: <strong>' . e($kodePelanggan) . '</strong>. Silakan masuk.';

            redirect('index.php?registered=1&username=' . urlencode($username));
        }
    }
}

render_header('Daftar Akun Pelanggan');
?>
<div class="row justify-content-center align-items-center py-4 py-md-5">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card shadow-lg border-0 overflow-hidden">
            <div class="card-body p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.6rem; border-radius: 14px;">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h1 class="h4 fw-bold mb-1">Daftar Akun Pelanggan</h1>
                    <p class="text-muted small mb-0">Buat akun untuk memantau status cucian dan nota digital laundry Anda.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="nama">
                            <i class="bi bi-person text-muted"></i> Nama Lengkap
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input class="form-control" id="nama" name="nama" placeholder="Contoh: Budi Santoso" value="<?= e($nama) ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="username">
                            <i class="bi bi-at text-muted"></i> Username
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input class="form-control" id="username" name="username" placeholder="contoh: budi_laundry" value="<?= e($username) ?>" autocomplete="username" required>
                        </div>
                        <div class="form-text small text-muted">Huruf kecil, angka, dan garis bawah (_) tanpa spasi.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">
                            <i class="bi bi-key text-muted"></i> Kata Sandi
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                            <input class="form-control" type="password" id="password" name="password" placeholder="Minimal 6 karakter" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password_confirm">
                            <i class="bi bi-check2-circle text-muted"></i> Ulangi Kata Sandi
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                            <input class="form-control" type="password" id="password_confirm" name="password_confirm" placeholder="Ketik ulang kata sandi" autocomplete="new-password" required>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6 mb-3 shadow-sm" type="submit">
                        <i class="bi bi-check-circle-fill me-1"></i> Selesaikan Pendaftaran
                    </button>
                </form>

                <div class="text-center pt-3 border-top">
                    <span class="text-muted small">Sudah memiliki akun?</span>
                    <a href="<?= e(app_url('index.php')) ?>" class="fw-bold text-primary text-decoration-none small ms-1">
                        Masuk di sini <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
