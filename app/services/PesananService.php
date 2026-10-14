<?php
namespace App\Services;

use App\Models\Pesanan;
use App\Models\DetailPesanan;
use App\Models\Layanan;
use App\Models\Nota;
use App\Models\Pembayaran;
use App\Models\SlotJadwal;
use App\Models\StatusPesanan;
use Database;

class PesananService {
    private Pesanan $pesananModel;
    private DetailPesanan $detailModel;
    private Layanan $layananModel;
    private SlotJadwal $slotModel;
    private StatusPesanan $statusModel;
    private Nota $notaModel;
    private Pembayaran $pembayaranModel;

    public function __construct() {
        $this->pesananModel = new Pesanan();
        $this->detailModel = new DetailPesanan();
        $this->layananModel = new Layanan();
        $this->slotModel = new SlotJadwal();
        $this->statusModel = new StatusPesanan();
        $this->notaModel = new Nota();
        $this->pembayaranModel = new Pembayaran();
    }

    public function generateKodePesanan(): string {
        return 'LND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    }

    public function generateNomorNota(): string {
        return 'INV/' . date('Ymd') . '/' . rand(1000, 9999);
    }

    public function generateKodePembayaran(): string {
        return 'PAY-' . date('YmdHis') . '-' . rand(100, 999);
    }

    public function createPesanan(array $data, array $items): int {
        require_once CONFIG_PATH . '/database.php';
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Hitung total berat & total harga dari items
            $totalBerat = 0;
            $totalHarga = 0;

            foreach ($items as $item) {
                $layanan = $this->layananModel->find($item['layanan_id']);
                if ($layanan) {
                    $berat = (float)$item['berat_kg'];
                    $subtotal = $berat * (float)$layanan['harga_per_kg'];
                    $totalBerat += $berat;
                    $totalHarga += $subtotal;
                }
            }

            // Jika slot jadwal belum ada atau dibuat otomatis
            $slotJadwalId = $data['slot_jadwal_id'] ?? null;
            if (!$slotJadwalId) {
                // Cari slot tersedia yang belum terpakai pesanan
                $availableSlots = $this->slotModel->getAvailableSlots();
                if (!empty($availableSlots)) {
                    $slotJadwalId = $availableSlots[0]['id'];
                } else {
                    // Buat slot unik dengan format detik agar tidak melanggar unik_slot (tanggal, jam_mulai, jam_selesai)
                    $jamMulai = date('H:i:s');
                    $jamSelesai = date('H:i:s', time() + 7200);
                    $slotJadwalId = $this->slotModel->create([
                        'tanggal'     => date('Y-m-d'),
                        'jam_mulai'   => $jamMulai,
                        'jam_selesai' => $jamSelesai,
                        'status'      => 'ditutup'
                    ]);
                }
            }

            $kodePesanan = $this->generateKodePesanan();

            $pesananId = $this->pesananModel->create([
                'kode_pesanan'      => $kodePesanan,
                'pelanggan_id'      => $data['pelanggan_id'],
                'kasir_id'          => $data['kasir_id'] ?? null,
                'slot_jadwal_id'    => $slotJadwalId,
                'tanggal_pesanan'   => date('Y-m-d H:i:s'),
                'total_berat'       => $totalBerat,
                'total_harga'       => $totalHarga,
                'total_dp'          => (float)($data['total_dp'] ?? 0),
                'status_pembayaran' => $data['status_pembayaran'] ?? 'belum_bayar',
                'status_cucian'     => 'menunggu',
                'catatan'           => $data['catatan'] ?? null,
            ]);

            // Insert detail item pesanan
            foreach ($items as $item) {
                $layanan = $this->layananModel->find($item['layanan_id']);
                if ($layanan) {
                    $berat = (float)$item['berat_kg'];
                    $subtotal = $berat * (float)$layanan['harga_per_kg'];

                    $this->detailModel->create([
                        'pesanan_id'   => $pesananId,
                        'layanan_id'   => $layanan['id'],
                        'nama_layanan' => $layanan['nama_layanan'],
                        'harga_per_kg' => $layanan['harga_per_kg'],
                        'berat_kg'     => $berat,
                        'subtotal'     => $subtotal,
                    ]);
                }
            }

            // Buat Riwayat status awal
            $this->statusModel->create([
                'pesanan_id'      => $pesananId,
                'status_cucian'   => 'menunggu',
                'keterangan'      => 'Pesanan baru dibuat dan menunggu diproses.',
                'diubah_oleh'     => $data['kasir_id'] ?? $data['pelanggan_id'],
                'waktu_perubahan' => date('Y-m-d H:i:s'),
            ]);

            // Buat Nota otomatis
            $this->notaModel->create([
                'pesanan_id'     => $pesananId,
                'nomor_nota'     => $this->generateNomorNota(),
                'tanggal_terbit' => date('Y-m-d H:i:s'),
                'catatan'        => 'Terima kasih telah mempercayakan cucian Anda kepada kami.'
            ]);

            // Jika ada pembayaran awal
            if (!empty($data['total_dp']) && $data['total_dp'] > 0) {
                $this->pembayaranModel->create([
                    'pesanan_id'       => $pesananId,
                    'kode_pembayaran'  => $this->generateKodePembayaran(),
                    'jenis_pembayaran' => ($data['status_pembayaran'] === 'lunas') ? 'pelunasan' : 'dp',
                    'jumlah'           => (float)$data['total_dp'],
                    'metode'           => $data['metode_pembayaran'] ?? 'tunai',
                    'status'           => 'berhasil',
                    'waktu_pembayaran' => date('Y-m-d H:i:s')
                ]);
            }

            $db->commit();
            return (int)$pesananId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function updateStatusCucian(int $pesananId, string $status, ?string $keterangan, ?int $userId): bool {
        $pesanan = $this->pesananModel->find($pesananId);
        if (!$pesanan) return false;

        $this->pesananModel->update($pesananId, [
            'status_cucian' => $status,
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        $this->statusModel->create([
            'pesanan_id'      => $pesananId,
            'status_cucian'   => $status,
            'keterangan'      => $keterangan ?: 'Status diubah menjadi: ' . ucwords(str_replace('_', ' ', $status)),
            'diubah_oleh'     => $userId,
            'waktu_perubahan' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    public function updateStatusBayar(int $pesananId, string $statusBayar, float $nominal, string $metode): bool {
        $pesanan = $this->pesananModel->find($pesananId);
        if (!$pesanan) return false;

        $this->pesananModel->update($pesananId, [
            'status_pembayaran' => $statusBayar,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        if ($nominal > 0) {
            $this->pembayaranModel->create([
                'pesanan_id'       => $pesananId,
                'kode_pembayaran'  => $this->generateKodePembayaran(),
                'jenis_pembayaran' => ($statusBayar === 'lunas') ? 'pelunasan' : 'dp',
                'jumlah'           => $nominal,
                'metode'           => $metode,
                'status'           => 'berhasil',
                'waktu_pembayaran' => date('Y-m-d H:i:s')
            ]);
        }

        return true;
    }

    public function submitQrisPayment(int $pesananId, float $nominal, ?string $buktiFile, bool $isDirectApproved = false): int {
        $pesanan = $this->pesananModel->find($pesananId);
        if (!$pesanan) throw new \Exception('Pesanan tidak ditemukan.');

        $status = $isDirectApproved ? 'berhasil' : 'menunggu';
        $jenis = ($nominal >= (float)$pesanan['total_harga']) ? 'pelunasan' : 'dp';

        $pembayaranId = $this->pembayaranModel->create([
            'pesanan_id'       => $pesananId,
            'kode_pembayaran'  => $this->generateKodePembayaran(),
            'jenis_pembayaran' => $jenis,
            'jumlah'           => $nominal,
            'metode'           => 'qris',
            'bukti_pembayaran' => $buktiFile,
            'status'           => $status,
            'waktu_pembayaran' => date('Y-m-d H:i:s')
        ]);

        if ($isDirectApproved) {
            $newStatus = ($jenis === 'pelunasan') ? 'lunas' : 'dp';
            $this->pesananModel->update($pesananId, [
                'status_pembayaran' => $newStatus,
                'updated_at'        => date('Y-m-d H:i:s')
            ]);
        }

        return (int)$pembayaranId;
    }

    public function konfirmasiPembayaran(int $pembayaranId, int $adminUserId): bool {
        $pembayaran = $this->pembayaranModel->find($pembayaranId);
        if (!$pembayaran) return false;

        $pesananId = (int)$pembayaran['pesanan_id'];
        $pesanan = $this->pesananModel->find($pesananId);
        if (!$pesanan) return false;

        // Ubah status pembayaran jadi berhasil
        $this->pembayaranModel->update($pembayaranId, [
            'status'           => 'berhasil',
            'waktu_pembayaran' => date('Y-m-d H:i:s')
        ]);

        // Hitung total terbayar yang berhasil
        $allPayments = $this->pembayaranModel->getByPesananId($pesananId);
        $totalPaid = 0;
        foreach ($allPayments as $p) {
            if ($p['id'] == $pembayaranId || $p['status'] === 'berhasil') {
                $totalPaid += (float)$p['jumlah'];
            }
        }

        $newStatusBayar = ($totalPaid >= (float)$pesanan['total_harga']) ? 'lunas' : 'dp';
        $this->pesananModel->update($pesananId, [
            'status_pembayaran' => $newStatusBayar,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // Catat riwayat
        $this->statusModel->create([
            'pesanan_id'      => $pesananId,
            'status_cucian'   => $pesanan['status_cucian'],
            'keterangan'      => 'Pembayaran QRIS sebesar ' . format_rupiah($pembayaran['jumlah']) . ' telah dikonfirmasi berhasil (' . strtoupper($newStatusBayar) . ').',
            'diubah_oleh'     => $adminUserId,
            'waktu_perubahan' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    public function tolakPembayaran(int $pembayaranId, ?string $alasan = null): bool {
        $pembayaran = $this->pembayaranModel->find($pembayaranId);
        if (!$pembayaran) return false;

        $this->pembayaranModel->update($pembayaranId, [
            'status' => 'ditolak'
        ]);

        return true;
    }
}

