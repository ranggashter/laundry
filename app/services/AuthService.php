<?php
namespace App\Services;

use App\Models\User;

class AuthService {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function attempt(string $email, string $password): bool {
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return false;
        }

        // Cek password hash atau plain text fallback jika ada legacy data
        $isValid = password_verify($password, $user['password']) || ($user['password'] === md5($password));

        if ($isValid) {
            // Simpan session user (hilangkan password dari session demi keamanan)
            unset($user['password']);
            $_SESSION['user'] = $user;
            return true;
        }

        return false;
    }

    public function register(array $data) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['role'] = $data['role'] ?? 'pelanggan';
        return $this->userModel->create($data);
    }

    public function logout(): void {
        unset($_SESSION['user']);
        session_destroy();
    }
}

