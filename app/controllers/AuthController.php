<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Validator\Validator;

class AuthController {
    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    public function showLoginForm(): void {
        view('auth.login', [
            'title' => 'Login - ' . APP_NAME
        ]);
    }

    public function login(): void {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $validator = Validator::make($_POST, [
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/login');
            return;
        }

        if ($this->authService->attempt($email, $password)) {
            flash('success', 'Selamat datang kembali, ' . auth_user()['nama'] . '!');
            redirect('/dashboard');
            return;
        }

        set_old(['email' => $email]);
        flash('error', 'Email atau password yang Anda masukkan salah.');
        redirect('/login');
    }

    public function showRegisterForm(): void {
        view('auth.register', [
            'title' => 'Daftar Akun - ' . APP_NAME
        ]);
    }

    public function register(): void {
        $validator = Validator::make($_POST, [
            'nama'     => 'required|min:3|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'no_hp'    => 'required|numeric|min:9',
            'alamat'   => 'required'
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/register');
            return;
        }

        $userId = $this->authService->register([
            'nama'     => trim($_POST['nama']),
            'email'    => trim($_POST['email']),
            'password' => trim($_POST['password']),
            'no_hp'    => trim($_POST['no_hp']),
            'alamat'   => trim($_POST['alamat']),
            'role'     => 'pelanggan'
        ]);

        if ($userId) {
            flash('success', 'Registrasi berhasil! Silakan login dengan akun baru Anda.');
            redirect('/login');
            return;
        }

        flash('error', 'Terjadi kesalahan saat pendaftaran. Silakan coba lagi.');
        redirect('/register');
    }

    public function logout(): void {
        $this->authService->logout();
        flash('success', 'Anda telah berhasil logout.');
        redirect('/login');
    }
}

