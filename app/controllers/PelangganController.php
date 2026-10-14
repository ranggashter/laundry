<?php
namespace App\Controllers;

use App\Models\User;
use App\Models\Pesanan;
use App\Validator\Validator;

class PelangganController {
    private User $userModel;
    private Pesanan $pesananModel;

    public function __construct() {
        $this->userModel = new User();
        $this->pesananModel = new Pesanan();
    }

    public function index(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        // Ambil data pelanggan beserta jumlah riwayat pesanannya
        $sql = "SELECT u.*, 
                       (SELECT COUNT(*) FROM pesanan p WHERE p.pelanggan_id = u.id) as total_pesanan
                FROM users u
                WHERE u.role = 'pelanggan'
                ORDER BY u.id DESC";
        $pelangganList = $this->userModel->rawQuery($sql);

        view('pelanggan.index', [
            'title'         => 'Data Pelanggan - ' . APP_NAME,
            'pelangganList' => $pelangganList
        ]);
    }

    public function create(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        view('pelanggan.create', [
            'title' => 'Tambah Pelanggan Baru - ' . APP_NAME
        ]);
    }

    public function store(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $noHp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $password = trim($_POST['password'] ?? '123456');

        // Jika email tidak diisi, buat email otomatis dari nomor HP
        if (empty($email) && !empty($noHp)) {
            $email = preg_replace('/[^0-9]/', '', $noHp) . '@pelanggan.laundry';
        }

        $_POST['email'] = $email;

        $validator = Validator::make($_POST, [
            'nama'   => 'required|min:3|max:100',
            'email'  => 'required|email|unique:users,email',
            'no_hp'  => 'required|numeric|min:8',
            'alamat' => 'required',
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/pelanggan/create');
            return;
        }

        $userId = $this->userModel->create([
            'nama'     => $nama,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'no_hp'    => $noHp,
            'alamat'   => $alamat,
            'role'     => 'pelanggan'
        ]);

        if ($userId) {
            flash('success', "Pelanggan '{$nama}' berhasil ditambahkan.");
            // Cek jika diarahkan dari form transaksi
            if (!empty($_POST['redirect_to_order'])) {
                redirect('/transaksi/create');
                return;
            }
            redirect('/pelanggan');
            return;
        }

        flash('error', 'Gagal menambahkan pelanggan. Silakan coba lagi.');
        redirect('/pelanggan/create');
    }

    public function edit(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $pelanggan = $this->userModel->find($id);
        if (!$pelanggan || $pelanggan['role'] !== 'pelanggan') {
            flash('error', 'Data pelanggan tidak ditemukan.');
            redirect('/pelanggan');
            return;
        }

        view('pelanggan.edit', [
            'title'     => 'Edit Data Pelanggan - ' . APP_NAME,
            'pelanggan' => $pelanggan
        ]);
    }

    public function update(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $pelanggan = $this->userModel->find($id);
        if (!$pelanggan) {
            flash('error', 'Pelanggan tidak ditemukan.');
            redirect('/pelanggan');
            return;
        }

        $validator = Validator::make($_POST, [
            'nama'   => 'required|min:3|max:100',
            'email'  => 'required|email|unique:users,email,' . $id,
            'no_hp'  => 'required|numeric|min:8',
            'alamat' => 'required',
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/pelanggan/edit/' . $id);
            return;
        }

        $updateData = [
            'nama'   => trim($_POST['nama']),
            'email'  => trim($_POST['email']),
            'no_hp'  => trim($_POST['no_hp']),
            'alamat' => trim($_POST['alamat']),
        ];

        // Jika password diisi baru
        if (!empty($_POST['password'])) {
            $updateData['password'] = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        }

        $this->userModel->update((int)$id, $updateData);

        flash('success', 'Data pelanggan berhasil diperbarui.');
        redirect('/pelanggan');
    }

    public function delete(string $id): void {
        if (!has_role('admin')) {
            flash('error', 'Hanya admin yang berhak menghapus data pelanggan.');
            redirect('/pelanggan');
            return;
        }

        // Cek apakah ada pesanan terkait pelanggan ini
        $pesananCount = $this->pesananModel->count("pelanggan_id = :pid", ['pid' => $id]);
        if ($pesananCount > 0) {
            flash('error', 'Pelanggan tidak dapat dihapus karena memiliki riwayat pesanan laundry.');
            redirect('/pelanggan');
            return;
        }

        $this->userModel->delete((int)$id);
        flash('success', 'Data pelanggan berhasil dihapus.');
        redirect('/pelanggan');
    }
}

