<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user'])) {
    redirect(role_home($_SESSION['user']['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
<div class="row justify-content-center align-items-center py-4">
    <div class="col-lg-6 col-xl-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Sistem Laundry</h1>
                <p class="text-secondary mb-4">Masuk sebagai admin, kasir, atau pelanggan.</p>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Kata sandi</label>
                        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Masuk</button>
                </form>
                <div class="alert alert-info mt-4 mb-0">
                    <strong>Akun demo:</strong> admin, kasir, pelanggan — kata sandi masing-masing <code>password</code>.
                    Gunakan akun ini untuk belajar di komputer lokal.
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
