<?php
// Fungsi dasar dan komponen tata letak bersama (Layout, Session, CSRF, Helpers).
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

function role_label(string $role): string
{
    $labels = [
        'admin' => 'Administrator',
        'kasir' => 'Kasir Laundry',
        'pelanggan' => 'Pelanggan',
    ];

    return $labels[$role] ?? ucfirst($role);
}

function role_badge_class(string $role): string
{
    $classes = [
        'admin' => 'badge-soft-primary',
        'kasir' => 'badge-soft-info',
        'pelanggan' => 'badge-soft-success',
    ];

    return $classes[$role] ?? 'badge-soft-secondary';
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

function status_icon(string $status): string
{
    $icons = [
        'Menunggu' => 'bi-hourglass-split',
        'Diproses' => 'bi-arrow-repeat',
        'Selesai' => 'bi-check2-circle',
        'Diambil' => 'bi-bag-check',
        'Belum Lunas' => 'bi-exclamation-circle',
        'Lunas' => 'bi-shield-check',
    ];

    return $icons[$status] ?? 'bi-circle';
}

function render_header(string $title): void
{
    $user = $_SESSION['user'] ?? null;
    $role = $user['role'] ?? '';
    $currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // Struktur menu sidebar terorganisir per peran pengguna
    $menus = [
        'admin' => [
            'group' => 'Menu Utama Admin',
            'items' => [
                ['Dashboard', 'admin/index.php', 'bi-grid-1x2-fill'],
                ['Kelola Layanan', 'admin/layanan.php', 'bi-tags-fill'],
                ['Laporan Keuangan', 'admin/laporan.php', 'bi-bar-chart-line-fill'],
            ],
        ],
        'kasir' => [
            'group' => 'Operasional Kasir',
            'items' => [
                ['Dashboard', 'kasir/index.php', 'bi-grid-1x2-fill'],
                ['Buat Pesanan Baru', 'kasir/buat_pesanan.php', 'bi-plus-circle-fill'],
                ['Catat Pembayaran', 'kasir/pembayaran.php', 'bi-credit-card-2-front-fill'],
                ['Status Cucian', 'kasir/update_status.php', 'bi-arrow-repeat'],
            ],
        ],
        'pelanggan' => [
            'group' => 'Layanan Pelanggan',
            'items' => [
                ['Dashboard & Status', 'pelanggan/index.php', 'bi-grid-1x2-fill'],
                ['Riwayat Transaksi', 'pelanggan/riwayat.php', 'bi-receipt-cutoff'],
            ],
        ],
    ];
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · LaundryApp Modern</title>
        
        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        
        <!-- Bootstrap 5 CSS CDN -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        
        <!-- Custom App Design System -->
        <link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body>
    <?php if ($user): ?>
    <div class="app-layout">
        <!-- Sidebar Navigation (Desktop Fixed & Mobile Offcanvas) -->
        <aside class="offcanvas-lg offcanvas-start app-sidebar" id="appSidebar" tabindex="-1" aria-labelledby="appSidebarLabel">
            <div class="sidebar-header">
                <a class="d-flex align-items-center gap-2 text-decoration-none" href="<?= e(app_url(role_home($role))) ?>">
                    <div class="brand-badge">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="brand-name">Laundry<span class="text-primary-gradient">App</span></span>
                    </div>
                </a>
                <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Tutup navigasi"></button>
            </div>

            <div class="sidebar-content">
                <div class="sidebar-heading"><?= e($menus[$role]['group'] ?? 'Navigasi') ?></div>
                <ul class="sidebar-nav mb-3">
                    <?php foreach ($menus[$role]['items'] ?? [] as [$label, $url, $icon]): ?>
                        <?php 
                        $isActive = str_ends_with($currentScript, '/' . $url) 
                            || (str_ends_with($url, 'index.php') && str_ends_with($currentScript, '/' . dirname($url) . '/'));
                        ?>
                        <li>
                            <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= e(app_url($url)) ?>">
                                <i class="bi <?= e($icon) ?>"></i>
                                <span><?= e($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="sidebar-heading">Akun & Pengaturan</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="sidebar-link" href="#" data-bs-toggle="modal" data-bs-target="#userProfileModal">
                            <i class="bi bi-person-circle"></i>
                            <span>Profil Saya</span>
                        </a>
                    </li>
                    <li>
                        <a class="sidebar-link text-danger" href="<?= e(app_url('logout.php')) ?>" title="Keluar dari akun">
                            <i class="bi bi-box-arrow-right text-danger"></i>
                            <span>Keluar</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-footer">
                <div class="user-profile-tile">
                    <div class="user-avatar-circle">
                        <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                    </div>
                    <div class="d-flex flex-column overflow-hidden me-auto">
                        <span class="fw-bold text-heading text-truncate small"><?= e($user['nama']) ?></span>
                        <span class="text-muted" style="font-size: 0.7rem;"><?= e(role_label($role)) ?></span>
                    </div>
                    <button class="btn btn-action btn-sm border-0" type="button" data-bs-toggle="modal" data-bs-target="#userProfileModal" title="Detail Profil">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="app-main-wrapper">
            <!-- Topbar Header -->
            <header class="app-topbar justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <button class="topbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-label="Buka navigasi">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="d-none d-sm-flex align-items-center gap-2 text-muted small">
                        <span class="fw-semibold text-heading"><?= e(role_label($role)) ?></span>
                        <span>/</span>
                        <span><?= e($title) ?></span>
                    </div>
                    <h2 class="h5 fw-bold text-heading mb-0 d-sm-none"><?= e($title) ?></h2>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="badge-soft badge-soft-secondary d-none d-md-inline-flex px-3 py-1">
                        <i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?>
                    </span>
                    <button class="btn btn-outline-secondary btn-sm rounded-pill d-none d-sm-inline-flex" type="button" data-bs-toggle="modal" data-bs-target="#userProfileModal">
                        <i class="bi bi-person-circle"></i>
                        <span>Profil</span>
                    </button>
                    <a class="btn btn-outline-danger btn-sm rounded-pill" href="<?= e(app_url('logout.php')) ?>" title="Keluar">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-sm-inline">Keluar</span>
                    </a>
                </div>
            </header>

            <!-- Page Main Body -->
            <main class="app-content container-fluid px-3 px-md-4">
    <?php else: ?>
    <!-- Public / Auth Layout for Logged Out User -->
    <div class="d-flex flex-column min-vh-100 justify-content-between">
        <header class="py-3 border-bottom bg-white no-print">
            <div class="container d-flex justify-content-between align-items-center">
                <a class="d-flex align-items-center gap-2 text-decoration-none" href="<?= e(app_url('index.php')) ?>">
                    <div class="brand-badge">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <span class="brand-name">Laundry<span class="text-primary-gradient">App</span></span>
                </a>
                <span class="badge-soft badge-soft-primary">
                    <i class="bi bi-shield-lock-fill me-1"></i> Portal Masuk
                </span>
            </div>
        </header>
        <main class="container py-4 my-auto">
    <?php endif; ?>
    <?php
}

function render_footer(): void
{
    $user = $_SESSION['user'] ?? null;
    $role = $user['role'] ?? '';
    ?>
    <?php if ($user): ?>
            </main>
            <!-- App Footer -->
            <footer class="app-footer no-print">
                <div class="container-fluid px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-heading"><i class="bi bi-droplet-half text-primary me-1"></i>LaundryApp</span>
                        <span class="text-muted">· Sistem Manajemen Laundry Modern</span>
                    </div>
                    <div class="text-muted small">
                        &copy; <?= date('Y') ?> Laundry Management System. Seluruh hak cipta dilindungi.
                    </div>
                </div>
            </footer>
        </div><!-- /.app-main-wrapper -->
    </div><!-- /.app-layout -->

    <!-- User Profile Modal -->
    <div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 bg-primary text-white p-4" style="background: var(--primary-gradient) !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white text-primary fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 52px; height: 52px; font-size: 1.35rem;">
                            <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0" id="userProfileModalLabel"><?= e($user['nama']) ?></h5>
                            <span class="badge bg-white text-primary mt-1 fw-semibold"><?= e(role_label($role)) ?></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush border rounded-3 mb-3">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><i class="bi bi-person me-2"></i>Username</span>
                            <span class="fw-bold font-monospace text-heading"><?= e($user['username']) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><i class="bi bi-shield-check me-2"></i>Hak Akses</span>
                            <span class="badge-soft <?= e(role_badge_class($role)) ?>"><?= e(strtoupper($role)) ?></span>
                        </div>
                        <?php if (!empty($user['kode_pelanggan'])): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><i class="bi bi-qr-code me-2"></i>Kode QR Pelanggan</span>
                            <span class="font-monospace fw-bold text-primary"><?= e($user['kode_pelanggan']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                        <div>Anda masuk sebagai <strong><?= e(role_label($role)) ?></strong> dengan izin operasional aktif.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a href="<?= e(app_url('logout.php')) ?>" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i> Keluar
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        </main>
        <!-- Public Footer -->
        <footer class="py-3 border-top bg-white no-print text-center text-muted small">
            <div class="container">
                &copy; <?= date('Y') ?> LaundryApp · Sistem Manajemen Laundry Bersih & Modern.
            </div>
        </footer>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- App Interactive JS -->
    <script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}
