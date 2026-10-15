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

    $error = 'Username atau kata sandi tidak sesuai. Silakan periksa kembali.';
}

render_header('Masuk ke Sistem');
?>
<div class="row justify-content-center align-items-center py-4 py-md-5">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <!-- Auth Card -->
        <div class="card shadow-lg border-0 overflow-hidden" style="border-radius: var(--radius-xl);">
            <div class="card-body p-4 p-sm-5">
                <!-- Brand Header -->
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-3 shadow" style="width: 56px; height: 56px; font-size: 1.75rem; border-radius: 16px;">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <h1 class="h4 fw-bold text-heading mb-1">Masuk ke Laundry<span class="text-primary-gradient">App</span></h1>
                    <p class="text-muted small mb-0">Kelola operasional laundry dengan cepat, rapi, dan terintegrasi.</p>
                </div>


                <?php if ($successMessage): ?>
                    <div class="alert alert-success mb-4 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div><?= e($successMessage) ?></div>
                    </div>
                <?php endif; ?>
                <!-- Alert Error -->

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="post" autocomplete="on">
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
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0" for="password">
                                <i class="bi bi-shield-lock text-muted"></i> Kata Sandi
                            </label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" title="Lihat/Sembunyikan sandi">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6 shadow-sm mb-3" type="submit">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                    </button>
                </form>

                <div class="text-center mt-3 pt-3 border-top">
                    <span class="text-muted small">Belum punya akun?</span>
                    <a href="<?= e(app_url('register.php')) ?>" class="fw-bold text-primary text-decoration-none small ms-1">
                        Daftar Akun Baru <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <!-- Demo Account Helper Card -->
                <div class="card bg-light border-0 p-3 mt-4" style="border-radius: var(--radius-md);">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2 text-primary fw-bold small">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <span>Akun Demo Cepat</span>
                        </div>
                        <span class="text-muted" style="font-size: 0.7rem;">Klik untuk isi otomatis</span>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-demo-fill text-start d-flex justify-content-between align-items-center" data-user="admin" data-pass="password">
                            <span><i class="bi bi-shield-check me-1"></i> Administrator</span>
                            <span class="badge bg-primary text-white font-monospace">admin</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info btn-demo-fill text-start d-flex justify-content-between align-items-center" data-user="kasir" data-pass="password">
                            <span><i class="bi bi-person-badge me-1"></i> Kasir Utama</span>
                            <span class="badge bg-info text-white font-monospace">kasir</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-demo-fill text-start d-flex justify-content-between align-items-center" data-user="pelanggan" data-pass="password">
                            <span><i class="bi bi-person me-1"></i> Pelanggan Demo</span>
                            <span class="badge bg-secondary text-white font-monospace">pelanggan</span>
                        </button>
                    </div>
                    <div class="text-center text-muted mt-2" style="font-size: 0.72rem;">
                        Kata sandi seluruh akun demo: <code class="fw-bold">password</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
