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
    $menus = [
        'admin' => [
            ['Dashboard', 'admin/index.php'],
            ['Layanan', 'admin/layanan.php'],
            ['Laporan', 'admin/laporan.php'],
        ],
        'kasir' => [
            ['Dashboard', 'kasir/index.php'],
            ['Buat Pesanan', 'kasir/buat_pesanan.php'],
            ['Pembayaran', 'kasir/pembayaran.php'],
            ['Status Cucian', 'kasir/update_status.php'],
        ],
        'pelanggan' => [
            ['Dashboard', 'pelanggan/index.php'],
            ['Riwayat', 'pelanggan/riwayat.php'],
        ],
    ];
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - Laundry App</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= e(app_url('index.php')) ?>">Laundry App</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <?php if ($user): ?>
                    <ul class="navbar-nav me-auto">
                        <?php foreach ($menus[$role] ?? [] as [$label, $url]): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url($url)) ?>"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <span class="navbar-text text-white me-lg-3"><?= e($user['nama']) ?></span>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(app_url('logout.php')) ?>">Keluar</a>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

function status_badge_class(string $status): string
{
    $classes = [
        'Menunggu' => 'text-bg-secondary',
        'Diproses' => 'text-bg-primary',
        'Selesai' => 'text-bg-success',
        'Diambil' => 'text-bg-dark',
        'Belum Lunas' => 'text-bg-warning',
        'Lunas' => 'text-bg-success',
    ];

    return $classes[$status] ?? 'text-bg-secondary';
}
