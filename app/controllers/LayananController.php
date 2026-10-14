<?php
namespace App\Controllers;

use App\Models\Layanan;
use App\Validator\Validator;

class LayananController {
    private Layanan $layananModel;

    public function __construct() {
        $this->layananModel = new Layanan();
    }

    public function index(): void {
        $layananList = $this->layananModel->all('nama_layanan ASC');
        view('produk.index', [
            'title'       => 'Daftar Layanan Laundry - ' . APP_NAME,
            'layananList' => $layananList
        ]);
    }

    public function create(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/layanan');
            return;
        }

        view('produk.create', [
            'title' => 'Tambah Layanan Baru - ' . APP_NAME
        ]);
    }

    public function store(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/layanan');
            return;
        }

        $validator = Validator::make($_POST, [
            'nama_layanan'  => 'required|min:3|max:50',
            'jenis_layanan' => 'required|in:reguler,express',
            'harga_per_kg'  => 'required|numeric',
            'estimasi_jam'  => 'required|numeric',
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/layanan/create');
            return;
        }

        $this->layananModel->create([
            'nama_layanan'  => trim($_POST['nama_layanan']),
            'jenis_layanan' => $_POST['jenis_layanan'],
            'harga_per_kg'  => (float)$_POST['harga_per_kg'],
            'estimasi_jam'  => (int)$_POST['estimasi_jam'],
            'deskripsi'     => trim($_POST['deskripsi'] ?? ''),
            'status'        => $_POST['status'] ?? 'aktif'
        ]);

        flash('success', 'Layanan berhasil ditambahkan.');
        redirect('/layanan');
    }

    public function edit(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/layanan');
            return;
        }

        $layanan = $this->layananModel->find($id);
        if (!$layanan) {
            flash('error', 'Layanan tidak ditemukan.');
            redirect('/layanan');
            return;
        }

        view('produk.edit', [
            'title'   => 'Edit Layanan - ' . APP_NAME,
            'layanan' => $layanan
        ]);
    }

    public function update(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/layanan');
            return;
        }

        $validator = Validator::make($_POST, [
            'nama_layanan'  => 'required|min:3|max:50',
            'jenis_layanan' => 'required|in:reguler,express',
            'harga_per_kg'  => 'required|numeric',
            'estimasi_jam'  => 'required|numeric',
        ]);

        if ($validator->fails()) {
            set_old($_POST);
            flash('error', $validator->firstError());
            redirect('/layanan/edit/' . $id);
            return;
        }

        $this->layananModel->update((int)$id, [
            'nama_layanan'  => trim($_POST['nama_layanan']),
            'jenis_layanan' => $_POST['jenis_layanan'],
            'harga_per_kg'  => (float)$_POST['harga_per_kg'],
            'estimasi_jam'  => (int)$_POST['estimasi_jam'],
            'deskripsi'     => trim($_POST['deskripsi'] ?? ''),
            'status'        => $_POST['status'] ?? 'aktif'
        ]);

        flash('success', 'Data layanan berhasil diperbarui.');
        redirect('/layanan');
    }

    public function delete(string $id): void {
        if (!has_role('admin')) {
            flash('error', 'Hanya admin yang dapat menghapus layanan.');
            redirect('/layanan');
            return;
        }

        $this->layananModel->delete((int)$id);
        flash('success', 'Layanan berhasil dihapus.');
        redirect('/layanan');
    }
}

