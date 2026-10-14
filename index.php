<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user'])) {
    redirect(role_home($_SESSION['user']['role']));
}

$error = '';
$successMessage = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Periksa token formulir sebelum memproses data login.
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Tanda tanya (?) membuat input pengguna tidak dianggap sebagai perintah SQL.
    $statement = getPDO()->prepare('SELECT id, nama, username, password, role, kode_pelanggan FROM users WHERE username = ? LIMIT 1');
    $statement->execute([$username]);
    $user = $statement->fetch();

    // Password dibandingkan dengan hash yang disimpan, bukan teks biasa.
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        redirect(role_home($user['role']));
    }

    $error = 'Username atau kata sandi tidak sesuai.';
}

render_header('Masuk');
?>
<div class="row justify-content-center align-items-center py-4 py-md-5">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card shadow-lg border-0 overflow-hidden">
            <div class="card-body p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.6rem; border-radius: 14px;">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <h1 class="h4 fw-bold mb-1">Masuk ke Laundry<span class="text-primary-gradient">App</span></h1>
                    <p class="text-muted small mb-0">Kelola operasional laundry dengan cepat, rapi, dan efisien.</p>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success mb-4 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div><?= $successMessage ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">
                            <i class="bi bi-person text-muted"></i> Username
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input class="form-control" id="username" name="username" placeholder="Masukkan username" value="<?= e($_GET['username'] ?? '') ?>" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">
                            <i class="bi bi-key text-muted"></i> Kata Sandi
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                            <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6 shadow-sm" type="submit">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                    </button>
                </form>

                <div class="text-center mt-3 pt-3 border-top">
                    <span class="text-muted small">Belum punya akun?</span>
                    <a href="<?= e(app_url('register.php')) ?>" class="fw-bold text-primary text-decoration-none small ms-1">
                        Daftar Akun Baru <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="card bg-light border-0 p-3 mt-4">
                    <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-semibold small">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Akun Demo Cepat (Password: <code>password</code>)</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-soft badge-soft-primary"><i class="bi bi-shield-check"></i> admin</span>
                        <span class="badge-soft badge-soft-info"><i class="bi bi-person-badge"></i> kasir</span>
                        <span class="badge-soft badge-soft-secondary"><i class="bi bi-person"></i> pelanggan</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
