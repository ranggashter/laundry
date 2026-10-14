<?php
namespace App\Controllers;

use App\Models\Pesanan;
use App\Models\DetailPesanan;
use App\Models\Layanan;
use App\Models\User;
use App\Models\Pembayaran;
use App\Models\StatusPesanan;
use App\Services\PesananService;
use App\Validator\Validator;

class PesananController {
    private Pesanan $pesananModel;
    private DetailPesanan $detailModel;
    private Layanan $layananModel;
    private User $userModel;
    private Pembayaran $pembayaranModel;
    private StatusPesanan $statusModel;
    private PesananService $pesananService;

    public function __construct() {
        $this->pesananModel = new Pesanan();
        $this->detailModel = new DetailPesanan();
        $this->layananModel = new Layanan();
        $this->userModel = new User();
        $this->pembayaranModel = new Pembayaran();
        $this->statusModel = new StatusPesanan();
        $this->pesananService = new PesananService();
    }

    public function index(): void {
        $user = auth_user();
        $pelangganId = ($user['role'] === 'pelanggan') ? (int)$user['id'] : null;

        $orders = $this->pesananModel->getAllWithDetails($pelangganId);

        view('transaksi.index', [
            'title'  => 'Daftar Pesanan - ' . APP_NAME,
            'orders' => $orders
        ]);
    }

    public function create(): void {
        $user = auth_user();
        $layananList = $this->layananModel->getActive();
        $pelangganList = ($user['role'] !== 'pelanggan') ? $this->userModel->getPelangganList() : [];

        view('transaksi.create', [
            'title'         => 'Buat Pesanan Baru - ' . APP_NAME,
            'layananList'   => $layananList,
            'pelangganList' => $pelangganList,
            'currentUser'   => $user
        ]);
    }

    public function store(): void {
        $user = auth_user();

        // Tentukan pelanggan_id
        $pelangganId = ($user['role'] === 'pelanggan')
            ? $user['id']
            : (int)($_POST['pelanggan_id'] ?? 0);

        if (!$pelangganId) {
            flash('error', 'Pelanggan harus dipilih.');
            redirect('/transaksi/create');
            return;
        }

        // Ambil data items layanan
        $layananIds = $_POST['layanan_id'] ?? [];
        $beratList = $_POST['berat_kg'] ?? [];

        $items = [];
        for ($i = 0; $i < count($layananIds); $i++) {
            $lId = (int)($layananIds[$i] ?? 0);
            $berat = (float)($beratList[$i] ?? 0);

            if ($lId > 0 && $berat > 0) {
                $items[] = [
                    'layanan_id' => $lId,
                    'berat_kg'   => $berat
                ];
            }
        }

        if (empty($items)) {
            flash('error', 'Silakan masukkan minimal 1 layanan dengan berat yang valid.');
            redirect('/transaksi/create');
            return;
        }

        try {
            $pesananId = $this->pesananService->createPesanan([
                'pelanggan_id'      => $pelangganId,
                'kasir_id'          => ($user['role'] !== 'pelanggan') ? $user['id'] : null,
                'total_dp'          => (float)($_POST['total_dp'] ?? 0),
                'status_pembayaran' => $_POST['status_pembayaran'] ?? 'belum_bayar',
                'metode_pembayaran' => $_POST['metode_pembayaran'] ?? 'tunai',
                'catatan'           => trim($_POST['catatan'] ?? ''),
            ], $items);

            flash('success', 'Pesanan baru berhasil dibuat!');
            redirect('/transaksi/detail/' . $pesananId);
        } catch (\Exception $e) {
            flash('error', 'Gagal membuat pesanan: ' . $e->getMessage());
            redirect('/transaksi/create');
        }
    }

    public function detail(string $id): void {
        $pesananId = (int)$id;
        $order = $this->pesananModel->getDetailById($pesananId);

        if (!$order) {
            flash('error', 'Pesanan tidak ditemukan.');
            redirect('/transaksi');
            return;
        }

        $user = auth_user();
        // Cek jika pelanggan hanya boleh lihat pesanannya sendiri
        if ($user['role'] === 'pelanggan' && $order['pelanggan_id'] != $user['id']) {
            flash('error', 'Akses ditolak.');
            redirect('/transaksi');
            return;
        }

        $items = $this->detailModel->getByPesananId($pesananId);
        $payments = $this->pembayaranModel->getByPesananId($pesananId);
        $statusHistory = $this->statusModel->getHistoryByPesananId($pesananId);

        view('transaksi.detail', [
            'title'         => 'Detail Pesanan #' . $order['kode_pesanan'] . ' - ' . APP_NAME,
            'order'         => $order,
            'items'         => $items,
            'payments'      => $payments,
            'statusHistory' => $statusHistory,
            'currentUser'   => $user
        ]);
    }

    public function updateStatus(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Pelanggan tidak dapat mengubah status cucian.');
            redirect('/transaksi/detail/' . $id);
            return;
        }

        $status = $_POST['status_cucian'] ?? '';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $userId = auth_user()['id'] ?? null;

        $validStatuses = ['menunggu', 'diproses', 'dicuci', 'dikeringkan', 'disetrika', 'siap_diambil', 'selesai', 'dibatalkan'];
        if (!in_array($status, $validStatuses, true)) {
            flash('error', 'Status cucian tidak valid.');
            redirect('/transaksi/detail/' . $id);
            return;
        }

        $this->pesananService->updateStatusCucian((int)$id, $status, $keterangan, $userId);
        flash('success', 'Status cucian berhasil diperbarui.');
        redirect('/transaksi/detail/' . $id);
    }

    public function bayar(string $id): void {
        if (has_role('pelanggan')) {
            flash('error', 'Hanya admin atau kasir yang dapat mengonfirmasi pembayaran.');
            redirect('/transaksi/detail/' . $id);
            return;
        }

        $statusBayar = $_POST['status_pembayaran'] ?? 'lunas';
        $nominal = (float)($_POST['nominal'] ?? 0);
        $metode = $_POST['metode'] ?? 'tunai';

        $this->pesananService->updateStatusBayar((int)$id, $statusBayar, $nominal, $metode);
        flash('success', 'Pembayaran berhasil dicatat.');
        redirect('/transaksi/detail/' . $id);
    }

    public function submitQris(string $id): void {
        $pesananId = (int)$id;
        $order = $this->pesananModel->find($pesananId);

        if (!$order) {
            flash('error', 'Pesanan tidak ditemukan.');
            redirect('/transaksi');
            return;
        }

        $nominal = (float)($_POST['nominal'] ?? 0);
        if ($nominal <= 0) {
            flash('error', 'Nominal pembayaran tidak valid.');
            redirect('/transaksi/detail/' . $id);
            return;
        }

        $uploadedFilename = null;
        if (!empty($_FILES['bukti_pembayaran']['name'])) {
            $file = $_FILES['bukti_pembayaran'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                flash('error', 'Format file bukti pembayaran tidak valid (Gunakan JPG, PNG, WEBP, atau PDF).');
                redirect('/transaksi/detail/' . $id);
                return;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                flash('error', 'Ukuran file bukti pembayaran maksimal 5MB.');
                redirect('/transaksi/detail/' . $id);
                return;
            }

            $uploadDir = ROOT_PATH . '/public/uploads/bukti/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadedFilename = 'bukti_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $uploadedFilename)) {
                flash('error', 'Gagal mengunggah bukti pembayaran.');
                redirect('/transaksi/detail/' . $id);
                return;
            }
        }

        // Jika kasir/admin yang input langsung di kasir, bisa langsung berstatus 'berhasil'
        $isDirectApproved = !has_role('pelanggan');

        try {
            $this->pesananService->submitQrisPayment($pesananId, $nominal, $uploadedFilename, $isDirectApproved);

            if ($isDirectApproved) {
                flash('success', 'Pembayaran QRIS berhasil dicatat.');
            } else {
                flash('success', 'Bukti pembayaran QRIS berhasil diunggah! Sedang menunggu verifikasi kasir/admin.');
            }
        } catch (\Exception $e) {
            flash('error', 'Gagal memproses QRIS: ' . $e->getMessage());
        }

        redirect('/transaksi/detail/' . $id);
    }

    public function konfirmasiPembayaran(string $pembayaranId): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $adminId = (int)auth_user()['id'];
        $pId = (int)$pembayaranId;

        $pembayaran = $this->pembayaranModel->find($pId);
        if (!$pembayaran) {
            flash('error', 'Data pembayaran tidak ditemukan.');
            redirect('/transaksi');
            return;
        }

        $this->pesananService->konfirmasiPembayaran($pId, $adminId);
        flash('success', 'Pembayaran QRIS berhasil dikonfirmasi dan status pesanan telah diperbarui!');

        $redirect = $_GET['redirect'] ?? ('/transaksi/detail/' . $pembayaran['pesanan_id']);
        redirect($redirect);
    }

    public function tolakPembayaran(string $pembayaranId): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $pId = (int)$pembayaranId;
        $pembayaran = $this->pembayaranModel->find($pId);
        if (!$pembayaran) {
            flash('error', 'Data pembayaran tidak ditemukan.');
            redirect('/transaksi');
            return;
        }

        $this->pesananService->tolakPembayaran($pId);
        flash('warning', 'Pembayaran QRIS telah ditolak.');

        $redirect = $_GET['redirect'] ?? ('/transaksi/detail/' . $pembayaran['pesanan_id']);
        redirect($redirect);
    }

    public function konfirmasiList(): void {
        if (has_role('pelanggan')) {
            flash('error', 'Akses ditolak.');
            redirect('/dashboard');
            return;
        }

        $pendingList = $this->pembayaranModel->getPendingPayments();

        view('transaksi.konfirmasi_pembayaran', [
            'title'       => 'Verifikasi Pembayaran QRIS - ' . APP_NAME,
            'pendingList' => $pendingList
        ]);
    }

    public function cetakNota(string $id): void {
        $pesananId = (int)$id;
        $order = $this->pesananModel->getDetailById($pesananId);

        if (!$order) {
            flash('error', 'Pesanan tidak ditemukan.');
            redirect('/transaksi');
            return;
        }

        $items = $this->detailModel->getByPesananId($pesananId);
        $payments = $this->pembayaranModel->getByPesananId($pesananId);

        view('transaksi.nota', [
            'order'    => $order,
            'items'    => $items,
            'payments' => $payments
        ]);
    }
}

