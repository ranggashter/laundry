<?php
// Fungsi dasar yang digunakan beberapa halaman, seperti sesi dan format Rupiah.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value): string
{
    // Escape teks sebelum masuk ke HTML agar teks tidak dianggap sebagai kode.
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    // Hilangkan nama folder peran agar tautan tetap benar dari halaman bersarang.
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim($directory, '/');
    if (preg_match('~/(admin|kasir|pelanggan)$~', $directory)) {
        $directory = substr($directory, 0, -strlen('/' . basename($directory)));
    }

    return $directory . ($path !== '' ? '/' . ltrim($path, '/') : '/');
}

function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    // Token disimpan di sesi dan dikirim bersama form untuk memeriksa permintaan.
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sentToken) || !hash_equals(csrf_token(), $sentToken)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

// Memastikan pengguna sudah login dan memiliki peran yang sesuai.
function require_role(string $role): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . app_url('index.php'));
        exit;
    }

    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke halaman ini.');
    }
}

// Menentukan halaman yang dibuka setelah login berhasil.
function role_home(string $role): string
{
    $pages = [
        'admin' => 'admin/index.php',
        'kasir' => 'kasir/index.php',
        'pelanggan' => 'pelanggan/index.php',
    ];

    return $pages[$role] ?? 'index.php';
}

function render_header(string $title): void
{
    // Bagian atas halaman dan menu dibuat sekali agar tidak disalin di semua file.
    $user = $_SESSION['user'] ?? null;
    $role = $user['role'] ?? '';
    $currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    $menus = [
        'admin' => [
            ['Dashboard', 'admin/index.php', 'bi-grid-1x2'],
            ['Layanan', 'admin/layanan.php', 'bi-tags'],
            ['Laporan', 'admin/laporan.php', 'bi-bar-chart-line'],
        ],
        'kasir' => [
            ['Dashboard', 'kasir/index.php', 'bi-grid-1x2'],
            ['Buat Pesanan', 'kasir/buat_pesanan.php', 'bi-plus-circle'],
            ['Pembayaran', 'kasir/pembayaran.php', 'bi-credit-card'],
            ['Status Cucian', 'kasir/update_status.php', 'bi-arrow-repeat'],
        ],
        'pelanggan' => [
            ['Dashboard', 'pelanggan/index.php', 'bi-grid-1x2'],
            ['Riwayat Nota', 'pelanggan/riwayat.php', 'bi-receipt'],
        ],
    ];
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · LaundryApp</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg app-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= e(app_url('index.php')) ?>">
                <div class="brand-badge">
                    <i class="bi bi-droplet-half"></i>
                </div>
                <span>Laundry<span class="text-primary-gradient">App</span></span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Buka navigasi">
                <i class="bi bi-list fs-3 text-dark"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <?php if ($user): ?>
                    <ul class="navbar-nav me-auto ps-lg-3 gap-1">
                        <?php foreach ($menus[$role] ?? [] as [$label, $url, $icon]): ?>
                            <?php $isActive = str_ends_with($currentScript, '/' . $url) || ($url === 'admin/index.php' && str_ends_with($currentScript, '/admin/')); ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= e(app_url($url)) ?>">
                                    <i class="bi <?= e($icon) ?>"></i>
                                    <span><?= e($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                        <div class="user-chip">
                            <div class="user-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
                            <div class="d-flex flex-column text-start">
                                <span class="lh-sm"><?= e($user['nama']) ?></span>
                                <span class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em;"><?= e($role) ?></span>
                            </div>
                        </div>
                        <a class="btn btn-outline-danger btn-sm" href="<?= e(app_url('logout.php')) ?>" title="Keluar dari akun">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-xl-inline">Keluar</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container py-4">
    <?php
}

function render_footer(): void
{
    // Tutup halaman dan muat JavaScript Bootstrap serta script aplikasi.
    ?>
    </main>
    <footer class="app-footer no-print">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold"><i class="bi bi-droplet-half text-primary me-1"></i>LaundryApp</span>
                <span class="text-muted">· Sistem Manajemen Laundry Modern & Bersih</span>
            </div>
            <div class="text-muted small">
                &copy; <?= date('Y') ?> Laundry Management System.
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

function status_badge_class(string $status): string
{
    $classes = [
        'Menunggu' => 'badge-soft-warning',
        'Diproses' => 'badge-soft-info',
        'Selesai' => 'badge-soft-success',
        'Diambil' => 'badge-soft-secondary',
        'Belum Lunas' => 'badge-soft-danger',
        'Lunas' => 'badge-soft-success',
    ];

    return $classes[$status] ?? 'badge-soft-secondary';
}
