<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user'])) {
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(role_home($_SESSION['user']['role']));
}

verify_csrf();
$pdo = getPDO();
$userId = (int) $_SESSION['user']['id'];
$usernameInput = $_POST['username'] ?? '';
$username = is_string($usernameInput) ? strtolower(trim($usernameInput)) : '';

$setFeedback = static function (string $type, string $message, string $username): void {
    $_SESSION['profile_feedback'] = [
        'type' => $type,
        'message' => $message,
        'username' => $username,
    ];
    redirect(role_home($_SESSION['user']['role']));
};

if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
    $setFeedback('danger', 'Username harus 3 sampai 30 karakter, hanya memakai huruf kecil, angka, atau garis bawah.', $username);
}

$accountQuery = $pdo->prepare('SELECT password, role FROM users WHERE id = ?');
$accountQuery->execute([$userId]);
$account = $accountQuery->fetch();
if (!$account) {
    session_unset();
    session_destroy();
    redirect('index.php');
}

$duplicate = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
$duplicate->execute([$username, $userId]);
if ($duplicate->fetch()) {
    $setFeedback('danger', 'Username sudah digunakan akun lain.', $username);
}

$newPassword = '';
if ($account['role'] === 'admin') {
    $currentPasswordInput = $_POST['current_password'] ?? '';
    $newPasswordInput = $_POST['new_password'] ?? '';
    $passwordConfirmInput = $_POST['password_confirm'] ?? '';
    $currentPassword = is_string($currentPasswordInput) ? $currentPasswordInput : '';
    $newPassword = is_string($newPasswordInput) ? $newPasswordInput : '';
    $passwordConfirm = is_string($passwordConfirmInput) ? $passwordConfirmInput : '';

    if (!password_verify($currentPassword, $account['password'])) {
        $setFeedback('danger', 'Password lama tidak sesuai. Perubahan tidak disimpan.', $username);
    }
    if ($newPassword !== '' && strlen($newPassword) < 6) {
        $setFeedback('danger', 'Password baru minimal 6 karakter.', $username);
    }
    if ($newPassword !== $passwordConfirm) {
        $setFeedback('danger', 'Konfirmasi password baru tidak cocok.', $username);
    }
} elseif ($account['role'] !== 'kasir' && $account['role'] !== 'pelanggan') {
    http_response_code(403);
    exit('Peran akun tidak diizinkan untuk memperbarui profil.');
} elseif (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['password_confirm'])) {
    http_response_code(403);
    exit('Hanya admin yang dapat mengubah password.');
}

try {
    if ($account['role'] === 'admin' && $newPassword !== '') {
        $update = $pdo->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?');
        $update->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        session_regenerate_id(true);
    } else {
        $update = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
        $update->execute([$username, $userId]);
    }

    $_SESSION['user']['username'] = $username;
    $_SESSION['user']['role'] = $account['role'];
    $setFeedback('success', 'Profil berhasil diperbarui.', $username);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $setFeedback('danger', 'Username sudah digunakan akun lain.', $username);
    }

    throw $exception;
}